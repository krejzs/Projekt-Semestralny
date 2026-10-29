<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo   = getPDO();
$bledy = [];

// --- odblokowanie konta (po zbyt wielu nieudanych logowaniach) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (($_POST['akcja'] ?? '') === 'odblokuj') {
        $stmt = $pdo->prepare('UPDATE users SET locked_until = NULL, failed_login_attempts = 0 WHERE id = :id');
        $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        flash('success', 'Konto zostało odblokowane.');
        redirect('/administrator/uzytkownicy.php');
    }
}

// --- wyszukiwanie ---
$szukaj = trim((string) ($_GET['szukaj'] ?? ''));

$gdzie     = '';
$parametry = [];
if ($szukaj !== '') {
    $gdzie = "WHERE (CONCAT(u.first_name, ' ', u.last_name) LIKE :szukaj1 OR u.email LIKE :szukaj2)";
    $parametry['szukaj1'] = '%' . $szukaj . '%';
    $parametry['szukaj2'] = '%' . $szukaj . '%';
}

// --- paginacja: 10 kont na strone ---
$na_stronie = 10;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users u ' . $gdzie);
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uzytkownicy as $u): ?>
                        <?php $zablokowane = $u['locked_until'] && strtotime((string) $u['locked_until']) > time(); ?>
                        <tr>
                            <td><?= (int) $u['id'] ?></td>
                            <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td><?= e(nazwa_roli($u['rola'])) ?></td>
                            <td><?= $u['is_email_verified'] ? 'tak' : 'nie' ?></td>
                            <td>
                                <?php if ($zablokowane): ?>
                                    <?= przycisk_post('odblokuj', (int) $u['id'], 'Odblokuj', 'przycisk-szary') ?>
                                <?php else: ?>
                                    -
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
