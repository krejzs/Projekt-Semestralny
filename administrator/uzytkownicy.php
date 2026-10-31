<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo   = getPDO();
$bledy = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $akcja = $_POST['akcja'] ?? '';
        $id    = (int) ($_POST['id'] ?? 0);

        if ($akcja === 'odblokuj') {
            $stmt = $pdo->prepare('UPDATE users SET locked_until = NULL, failed_login_attempts = 0 WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash('success', 'Konto zostało odblokowane.');
            redirect('/administrator/uzytkownicy.php');

        } elseif ($akcja === 'usun') {
            if ($id === (int) current_user_id()) {
                flash('error', 'Nie możesz usunąć własnego konta.');
            } else {
                $blad = usun_konto($pdo, $id);

                if ($blad === null) {
                    flash('success', 'Konto zostało usunięte.');
                } else {
                    flash('error', $blad);
                }
            }
            redirect('/administrator/uzytkownicy.php');
        }
    }
}

$szukaj     = trim((string) ($_GET['szukaj'] ?? ''));
$filtr_rola = (string) ($_GET['rola'] ?? '');
if (!in_array($filtr_rola, wszystkie_role(), true)) {
    $filtr_rola = '';
}

$warunki   = [];
$parametry = [];

if ($szukaj !== '') {
    $warunki[]            = "(CONCAT(u.first_name, ' ', u.last_name) LIKE :szukaj1 OR u.email LIKE :szukaj2)";
    $parametry['szukaj1'] = '%' . $szukaj . '%';
    $parametry['szukaj2'] = '%' . $szukaj . '%';
}
if ($filtr_rola !== '') {
    $warunki[]          = 'r.name = :rola';
    $parametry['rola']  = $filtr_rola;
}

$gdzie = empty($warunki) ? '' : 'WHERE ' . implode(' AND ', $warunki);

$na_stronie = 10;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id ' . $gdzie);
$stmt->execute($parametry);
$razem = (int) $stmt->fetchColumn();

$ile_stron  = max(1, (int) ceil($razem / $na_stronie));
$strona     = min(numer_strony(), $ile_stron);
$od_wiersza = ($strona - 1) * $na_stronie;

$stmt = $pdo->prepare(
    'SELECT u.id, u.first_name, u.last_name, u.email, u.is_email_verified, u.locked_until, r.name AS rola
     FROM users u
     JOIN roles r ON r.id = u.role_id '
    . $gdzie .
    ' ORDER BY u.id ASC
      LIMIT :limit OFFSET :offset'
);
foreach ($parametry as $nazwa => $wartosc) {
    $stmt->bindValue(':' . $nazwa, $wartosc);
}
$stmt->bindValue(':limit', $na_stronie, PDO::PARAM_INT);
$stmt->bindValue(':offset', $od_wiersza, PDO::PARAM_INT);
$stmt->execute();
$uzytkownicy = $stmt->fetchAll();

$tytul = 'Uczniowie i konta';
require __DIR__ . '/../czesci_wspolne/admin_gora.php';
?>

<div class="karta">
    <form method="get" action="<?= BASE_URL ?>/administrator/uzytkownicy.php" class="filtry">
        <label>Szukaj (imię, nazwisko lub e-mail)
            <input type="text" name="szukaj" value="<?= e($szukaj) ?>">
        </label>
        <label>Rola
            <select name="rola">
                <option value="">Wszystkie</option>
                <?php foreach (wszystkie_role() as $kod): ?>
                    <option value="<?= e($kod) ?>" <?= $kod === $filtr_rola ? 'selected' : '' ?>>
                        <?= e(nazwa_roli($kod)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div>
            <button type="submit" class="przycisk">Szukaj</button>
            <a href="<?= BASE_URL ?>/administrator/uzytkownicy.php" class="przycisk przycisk-szary">Wyczyść</a>
        </div>
    </form>
</div>

<div class="karta">
    <h2>Konta w systemie: <?= $razem ?></h2>

    <?php if (empty($uzytkownicy)): ?>
        <p>Nie znaleziono żadnych kont.</p>
    <?php else: ?>
        <div class="tabela-przewijana">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imię i nazwisko</th>
                        <th>E-mail</th>
                        <th>Rola</th>
                        <th>E-mail potwierdzony</th>
                        <th>Blokada</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uzytkownicy as $u): ?>
                        <?php $zablokowane = $u['locked_until'] && strtotime((string) $u['locked_until']) > time(); ?>
                        <tr>
                            <td><?= (int) $u['id'] ?></td>
                            <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <?= e(nazwa_roli($u['rola'])) ?>
                                <?= (int) $u['id'] === (int) current_user_id() ? '(to Ty)' : '' ?>
                            </td>
                            <td><?= $u['is_email_verified'] ? 'tak' : 'nie' ?></td>
                            <td>
                                <?php if ($zablokowane): ?>
                                    <?= przycisk_post('odblokuj', (int) $u['id'], 'Odblokuj', 'przycisk-szary') ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['rola'] === 'admin' || (int) $u['id'] === (int) current_user_id()): ?>
                                    -
                                <?php else: ?>
                                    <?= przycisk_post('usun', (int) $u['id'], 'Usuń', 'przycisk-czerwony', 'Czy na pewno usunąć konto ' . $u['first_name'] . ' ' . $u['last_name'] . '? Tej operacji nie można cofnąć.') ?>
                                <?php endif; ?>
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
