<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

// rezerwowac moze tylko uczen (klient)
require_role(['client']);

$pdo   = getPDO();
$bledy = [];

// Proces ma kroki: lekcja -> lektor -> data -> godzina -> potwierdzenie.
// Wybory przechodza w adresie strony (GET), a samo potwierdzenie idzie formularzem (POST).
$zrodlo = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$id_lekcji  = (int) ($zrodlo['usluga'] ?? 0);
$id_lektora = (int) ($zrodlo['lektor'] ?? 0);
$data       = trim((string) ($zrodlo['data'] ?? ''));
$godzina    = trim((string) ($zrodlo['godzina'] ?? ''));

// --- krok 1: lekcja (wybierana w ofercie) ---
$lekcja = $id_lekcji > 0 ? pobierz_lekcje($pdo, $id_lekcji) : null;

if (!$lekcja) {
    flash('error', 'Najpierw wybierz lekcję z oferty.');
    redirect('/publiczne/uslugi.php');
}

$czas     = (int) $lekcja['duration_minutes'];
$lektorzy = lektorzy_lekcji($pdo, $id_lekcji);

// --- krok 2: lektor (musi byc jednym z lektorow tej lekcji) ---
$lektor = null;
foreach ($lektorzy as $kandydat) {
    if ((int) $kandydat['id'] === $id_lektora) {
        $lektor = $kandydat;
    }
}

if ($id_lektora > 0 && $lektor === null) {
    $bledy[]    = 'Wybrany lektor nie prowadzi tej lekcji.';
    $id_lektora = 0;
    $data       = '';
    $godzina    = '';
}

// --- potwierdzenie rezerwacji (formularz POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
        $godzina = '';
    } elseif ($lektor !== null) {
        // cala walidacja jest w zapisz_rezerwacje() - tam sprawdzamy wszystko od nowa
        $blad = zapisz_rezerwacje($pdo, (int) current_user_id(), $id_lekcji, $id_lektora, $data, $godzina);

        if ($blad === null) {
            flash('success', 'Lekcja została zarezerwowana. Czeka na potwierdzenie przez lektora.');
            redirect('/klient/panel.php');
        }

        // nie udalo sie - wracamy do wyboru godziny z komunikatem
        $bledy[] = $blad;
        $godzina = '';
    }
}

// --- ustalamy, na ktorym kroku jestesmy ---
$krok  = 2;
$wolne = [];

if ($lektor !== null) {
    $krok = 3;

    if ($data !== '') {
        if (!poprawna_data($data)) {
            $bledy[] = 'Podana data ma nieprawidłowy format.';
            $data    = '';
            $godzina = '';
        } else {
            $wolne = wolne_godziny($pdo, $id_lektora, $data, $czas);

            if (empty($wolne)) {
                $bledy[] = 'W wybranym dniu nie ma wolnych godzin. Wybierz inny dzień.';
                $data    = '';
                $godzina = '';
            } else {
                $krok = 4;

                if ($godzina !== '') {
                    if (in_array($godzina, $wolne, true)) {
                        $krok = 5;
                    } else {
                        $bledy[] = 'Wybrana godzina jest niedostępna. Wybierz inną.';
                        $godzina = '';
                    }
                }
            }
        }
    }
}

// krok 3: najblizsze 14 dni z liczba wolnych godzin
$dni = [];
if ($krok === 3) {
    for ($i = 0; $i < 14; $i++) {
        $dzien = date('Y-m-d', strtotime('+' . $i . ' days'));
        $dni[] = [
            'data'  => $dzien,
            'numer' => (int) date('N', strtotime($dzien)),
            'wolne' => count(wolne_godziny($pdo, $id_lektora, $dzien, $czas)),
        ];
    }
}

$podstawa = BASE_URL . '/klient/rezerwacja.php?usluga=' . $id_lekcji;
$nazwy_krokow = [1 => 'Lekcja', 2 => 'Lektor', 3 => 'Data', 4 => 'Godzina', 5 => 'Potwierdzenie'];

$tytul = 'Rezerwacja lekcji';
require __DIR__ . '/../czesci_wspolne/strona_gora.php';
?>

<ol class="kroki">
    <?php foreach ($nazwy_krokow as $numer => $nazwa): ?>
        <li class="<?= $numer < $krok ? 'zrobiony' : ($numer === $krok ? 'aktualny' : '') ?>">
            <?= $numer ?>. <?= e($nazwa) ?>
        </li>
    <?php endforeach; ?>
</ol>

<div class="karta">
    <strong><?= e($lekcja['jezyk']) ?>: <?= e($lekcja['name']) ?></strong>
    (<?= $czas ?> min, <?= e(number_format((float) $lekcja['price'], 2, ',', ' ')) ?> zł)
    <?php if ($lektor !== null): ?>
        <br>Lektor: <?= e($lektor['first_name'] . ' ' . $lektor['last_name']) ?>
        <a href="<?= e($podstawa) ?>">(zmień)</a>
    <?php endif; ?>
    <?php if ($krok >= 4): ?>
        <br>Data: <?= e(date('d.m.Y', strtotime($data))) ?>
        <a href="<?= e($podstawa . '&lektor=' . $id_lektora) ?>">(zmień)</a>
    <?php endif; ?>
</div>

<?php if ($krok === 2): ?>

    <div class="karta">
        <h2>Wybierz lektora</h2>

        <?php if (empty($lektorzy)): ?>
            <p>Obecnie żaden lektor nie prowadzi tej lekcji.</p>
        <?php else: ?>
            <div class="godziny">
                <?php foreach ($lektorzy as $l): ?>
                    <a href="<?= e($podstawa . '&lektor=' . (int) $l['id']) ?>" class="przycisk przycisk-szary">
                        <?= e($l['first_name'] . ' ' . $l['last_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($krok === 3): ?>

    <div class="karta">
        <h2>Wybierz dzień</h2>

        <div class="dni">
            <?php foreach ($dni as $d): ?>
                <?php
                $opis = skrot_dnia($d['numer']) . ' ' . date('d.m', strtotime($d['data']));
                $adres = $podstawa . '&lektor=' . $id_lektora . '&data=' . urlencode($d['data']);
                ?>
                <?php if ($d['wolne'] > 0): ?>
                    <a class="dzien" href="<?= e($adres) ?>">
                        <?= e($opis) ?>
                        <small>wolne: <?= $d['wolne'] ?></small>
                    </a>
                <?php else: ?>
                    <span class="dzien dzien-brak">
                        <?= e($opis) ?>
                        <small>brak</small>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <form method="get" action="<?= BASE_URL ?>/klient/rezerwacja.php" class="filtry">
            <input type="hidden" name="usluga" value="<?= $id_lekcji ?>">
            <input type="hidden" name="lektor" value="<?= $id_lektora ?>">
            <label>Albo wybierz inną datę (do <?= MAX_DNI_NAPRZOD ?> dni do przodu)
                <input type="date" name="data"
                       min="<?= e(date('Y-m-d')) ?>"
                       max="<?= e(date('Y-m-d', strtotime('+' . MAX_DNI_NAPRZOD . ' days'))) ?>" required>
            </label>
            <div>
                <button type="submit" class="przycisk przycisk-szary">Pokaż godziny</button>
            </div>
        </form>
    </div>

<?php elseif ($krok === 4): ?>

    <div class="karta">
        <h2>Wybierz godzinę rozpoczęcia</h2>
        <p>Lekcja trwa <?= $czas ?> minut. Pokazujemy tylko wolne godziny.</p>

        <div class="godziny">
            <?php foreach ($wolne as $g): ?>
                <a class="przycisk przycisk-szary"
                   href="<?= e($podstawa . '&lektor=' . $id_lektora . '&data=' . urlencode($data) . '&godzina=' . urlencode($g)) ?>">
                    <?= e($g) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

<?php else: ?>

    <div class="karta">
        <h2>Potwierdź rezerwację</h2>

        <table class="podsumowanie">
            <tbody>
                <tr><th>Lekcja</th><td><?= e($lekcja['jezyk']) ?>: <?= e($lekcja['name']) ?></td></tr>
                <tr><th>Lektor</th><td><?= e($lektor['first_name'] . ' ' . $lektor['last_name']) ?></td></tr>
                <tr>
                    <th>Termin</th>
                    <td>
                        <?= e(nazwa_dnia((int) date('N', strtotime($data)))) ?>,
                        <?= e(date('d.m.Y', strtotime($data))) ?>,
                        <?= e($godzina) ?>-<?= e(z_minut(na_minuty($godzina) + $czas)) ?>
                    </td>
                </tr>
                <tr><th>Cena</th><td><?= e(number_format((float) $lekcja['price'], 2, ',', ' ')) ?> zł</td></tr>
            </tbody>
        </table>

        <form method="post" action="<?= BASE_URL ?>/klient/rezerwacja.php" style="margin-top:18px;">
            <?= csrf_field() ?>
            <input type="hidden" name="usluga" value="<?= $id_lekcji ?>">
            <input type="hidden" name="lektor" value="<?= $id_lektora ?>">
            <input type="hidden" name="data" value="<?= e($data) ?>">
            <input type="hidden" name="godzina" value="<?= e($godzina) ?>">

            <button type="submit" class="przycisk">Potwierdzam rezerwację</button>
            <a href="<?= e($podstawa . '&lektor=' . $id_lektora . '&data=' . urlencode($data)) ?>" class="przycisk przycisk-szary">Zmień godzinę</a>
        </form>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/../czesci_wspolne/strona_dol.php'; ?>
