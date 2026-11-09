<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

// ta strona jest dla wszystkich (takze niezalogowanych)
$pdo = getPDO();

$jezyki      = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$filtr_jezyk = (int) ($_GET['jezyk'] ?? 0);

// tylko aktywne lekcje + liczba lektorow, ktorzy je prowadza i maja ustawione godziny pracy
$sql = 'SELECT s.id, s.name, s.description, s.price, s.duration_minutes, c.name AS jezyk,
               (SELECT COUNT(*)
                FROM employee_services es
                JOIN employees e ON e.id = es.employee_id
                WHERE es.service_id = s.id
                  AND e.active = 1
                  AND EXISTS (SELECT 1 FROM employee_availability a WHERE a.employee_id = e.id)) AS lektorzy
        FROM services s
        JOIN categories c ON c.id = s.category_id
        WHERE s.active = 1';
$parametry = [];

if ($filtr_jezyk > 0) {
    $sql .= ' AND s.category_id = :jezyk';
    $parametry['jezyk'] = $filtr_jezyk;
}
$sql .= ' ORDER BY c.name, s.name';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametry);
$lekcje = $stmt->fetchAll();

// po wybraniu jezyka pokazujemy tez lektorow, ktorzy go ucza
$nazwa_jezyka     = '';
$lektorzy_jezyka  = [];
if ($filtr_jezyk > 0) {
    foreach ($jezyki as $j) {
        if ((int) $j['id'] === $filtr_jezyk) {
            $nazwa_jezyka = $j['name'];
        }
    }
    $lektorzy_jezyka = lektorzy_jezyka($pdo, $filtr_jezyk);
}

$moze_rezerwowac = !is_logged_in() || current_role() === 'client';

$tytul = 'Oferta lekcji';
require __DIR__ . '/../czesci_wspolne/strona_gora.php';
?>

<div class="karta">
    <form method="get" action="<?= BASE_URL ?>/publiczne/uslugi.php" class="filtry">
        <label>Pokaż lekcje z języka
            <select name="jezyk">
                <option value="0">Wszystkie języki</option>
                <?php foreach ($jezyki as $j): ?>
                    <option value="<?= (int) $j['id'] ?>" <?= (int) $j['id'] === $filtr_jezyk ? 'selected' : '' ?>>
                        <?= e($j['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div>
            <button type="submit" class="przycisk">Filtruj</button>
        </div>
    </form>
</div>

<?php if ($filtr_jezyk > 0 && $nazwa_jezyka !== ''): ?>
    <div class="karta">
        <h2>Lektorzy uczący języka: <?= e($nazwa_jezyka) ?></h2>

        <?php if (empty($lektorzy_jezyka)): ?>
            <p>Obecnie żaden lektor nie ma dostępnych lekcji z tego języka.</p>
        <?php else: ?>
            <p>
                <?php foreach ($lektorzy_jezyka as $i => $lj): ?>
                    <?= $i > 0 ? ', ' : '' ?><?= e($lj['first_name'] . ' ' . $lj['last_name']) ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (empty($lekcje)): ?>
    <p>Nie ma teraz żadnych lekcji w ofercie.</p>
<?php else: ?>
    <div class="siatka-ofert">
        <?php foreach ($lekcje as $l): ?>
            <div class="oferta">
                <div><span class="status"><?= e($l['jezyk']) ?></span></div>
                <h2><?= e($l['name']) ?></h2>
                <p class="oferta-opis"><?= e(mb_strimwidth((string) ($l['description'] ?? ''), 0, 140, '...')) ?></p>
                <p class="oferta-dane">
                    <?= (int) $l['duration_minutes'] ?> min &middot;
                    <strong><?= e(number_format((float) $l['price'], 2, ',', ' ')) ?> zł</strong>
                </p>
                <p class="oferta-lektorzy">
                    <?php if ((int) $l['lektorzy'] > 0): ?>
                        Dostępni lektorzy: <?= (int) $l['lektorzy'] ?>
                    <?php else: ?>
                        Brak dostępnych lektorów
                    <?php endif; ?>
                </p>
                <div class="akcje">
                    <a href="<?= BASE_URL ?>/publiczne/usluga.php?id=<?= (int) $l['id'] ?>" class="przycisk przycisk-szary">Szczegóły</a>
                    <?php if ($moze_rezerwowac && (int) $l['lektorzy'] > 0): ?>
                        <a href="<?= BASE_URL ?>/klient/rezerwacja.php?usluga=<?= (int) $l['id'] ?>" class="przycisk">Zarezerwuj</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../czesci_wspolne/strona_dol.php'; ?>
