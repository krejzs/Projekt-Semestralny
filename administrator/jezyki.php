<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo   = getPDO();
$bledy = [];

// dane do formularza (id = 0 oznacza dodawanie nowego jezyka)
$formularz_id    = 0;
$formularz_nazwa = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $akcja = $_POST['akcja'] ?? '';
        $id    = (int) ($_POST['id'] ?? 0);

        if ($akcja === 'zapisz') {
            $formularz_id    = $id;
            $formularz_nazwa = trim((string) ($_POST['nazwa'] ?? ''));

            if ($formularz_nazwa === '') {
                $bledy[] = 'Podaj nazwę języka.';
            } elseif (mb_strlen($formularz_nazwa) > 80) {
                $bledy[] = 'Nazwa może mieć maksymalnie 80 znaków.';
            }

            // nazwa musi byc unikalna (nie liczac edytowanego jezyka)
            if (empty($bledy)) {
                $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = :nazwa AND id <> :id');
                $stmt->execute(['nazwa' => $formularz_nazwa, 'id' => $id]);
                if ($stmt->fetch()) {
                    $bledy[] = 'Taki język już jest na liście.';
                }
            }

            if (empty($bledy)) {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE categories SET name = :nazwa WHERE id = :id');
                    $stmt->execute(['nazwa' => $formularz_nazwa, 'id' => $id]);
                    flash('success', 'Zapisano zmiany.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:nazwa)');
                    $stmt->execute(['nazwa' => $formularz_nazwa]);
                    flash('success', 'Dodano język.');
                }
                redirect('/administrator/jezyki.php');
            }
        } elseif ($akcja === 'usun') {
            // nie usuwamy jezyka, do ktorego sa przypisane lekcje
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM services WHERE category_id = :id');
            $stmt->execute(['id' => $id]);

            if ((int) $stmt->fetchColumn() > 0) {
                flash('error', 'Nie można usunąć języka, do którego są przypisane lekcje. Najpierw usuń lub przenieś te lekcje.');
            } else {
                $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                $stmt->execute(['id' => $id]);
                flash('success', 'Język został usunięty.');
            }
            redirect('/administrator/jezyki.php');
        }
    }
}

// wejscie w edycje: jezyki.php?edytuj=3
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edytuj'])) {
    $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edytuj']]);
    $jezyk = $stmt->fetch();

    if ($jezyk) {
        $formularz_id    = (int) $jezyk['id'];
        $formularz_nazwa = $jezyk['name'];
    }
}

// lista jezykow razem z liczba lekcji w kazdym
$jezyki = $pdo->query(
    'SELECT c.id, c.name, COUNT(s.id) AS ile_lekcji
     FROM categories c
     LEFT JOIN services s ON s.category_id = c.id
     GROUP BY c.id, c.name
     ORDER BY c.name'
)->fetchAll();

$tytul = 'Języki';
require __DIR__ . '/../czesci_wspolne/admin_gora.php';
?>

<div class="karta">
    <h2><?= $formularz_id > 0 ? 'Edytuj język' : 'Dodaj język' ?></h2>

    <form method="post" action="<?= BASE_URL ?>/administrator/jezyki.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="akcja" value="zapisz">
        <input type="hidden" name="id" value="<?= $formularz_id ?>">

        <label>Nazwa języka
            <input type="text" name="nazwa" value="<?= e($formularz_nazwa) ?>" maxlength="80" required>
        </label>

        <button type="submit" class="przycisk">Zapisz</button>
        <?php if ($formularz_id > 0): ?>
            <a href="<?= BASE_URL ?>/administrator/jezyki.php" class="przycisk przycisk-szary">Anuluj</a>
        <?php endif; ?>
    </form>
</div>

<div class="karta">
    <h2>Wszystkie języki</h2>

    <?php if (empty($jezyki)): ?>
        <p>Nie ma jeszcze żadnych języków. Dodaj pierwszy powyżej.</p>
    <?php else: ?>
        <div class="tabela-przewijana">
            <table>
                <thead>
                    <tr>
                        <th>Język</th>
                        <th>Liczba lekcji</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jezyki as $j): ?>
                        <tr>
                            <td><?= e($j['name']) ?></td>
                            <td><?= (int) $j['ile_lekcji'] ?></td>
                            <td>
                                <div class="akcje">
                                    <a href="?edytuj=<?= (int) $j['id'] ?>" class="przycisk przycisk-maly przycisk-szary">Edytuj</a>
                                    <?= przycisk_post('usun', (int) $j['id'], 'Usuń', 'przycisk-czerwony', 'Czy na pewno usunąć język "' . $j['name'] . '"?') ?>
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
