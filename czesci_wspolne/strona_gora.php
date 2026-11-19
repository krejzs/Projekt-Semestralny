<?php
// Gorna czesc stron dla gosci, uczniow i lektorow (oferta, panel ucznia, panel lektora).
// Strona, ktora to dolacza, musi wczesniej ustawic zmienna $tytul.
// Moze tez ustawic $bledy (lista bledow do pokazania).

$komunikat_ok   = flash('success');
$komunikat_blad = flash('error');
$rola           = current_role();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tytul) ?> - Szkoła językowa</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/zasoby/styl.css">
    <!-- styl_admin.css ma tez wspolne elementy: karty, przyciski, tabele, statusy -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/zasoby/styl_admin.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/zasoby/styl_strona.css">
</head>
<body>

<header class="strona-naglowek">
    <div class="strona-naglowek-wnetrze">
        <a class="strona-logo" href="<?= BASE_URL ?>/publiczne/uslugi.php">Szkoła językowa</a>

        <nav class="strona-menu">
            <a href="<?= BASE_URL ?>/publiczne/uslugi.php">Oferta lekcji</a>

            <?php if (!is_logged_in()): ?>
                <a href="<?= BASE_URL ?>/index.php">Zaloguj się</a>
                <a href="<?= BASE_URL ?>/publiczne/rejestracja.php">Rejestracja</a>
            <?php elseif ($rola === 'client'): ?>
                <a href="<?= BASE_URL ?>/klient/panel.php">Moje zapisy</a>
                <a href="<?= BASE_URL ?>/publiczne/profil.php">Mój profil</a>
                <a href="<?= BASE_URL ?>/publiczne/wylogowanie.php">Wyloguj się</a>
            <?php elseif ($rola === 'employee'): ?>
                <a href="<?= BASE_URL ?>/pracownik/panel.php">Moje wizyty</a>
                <a href="<?= BASE_URL ?>/publiczne/profil.php">Mój profil</a>
                <a href="<?= BASE_URL ?>/publiczne/wylogowanie.php">Wyloguj się</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/administrator/panel.php">Panel administratora</a>
                <a href="<?= BASE_URL ?>/publiczne/wylogowanie.php">Wyloguj się</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="strona-tresc">
    <h1><?= e($tytul) ?></h1>

    <?php if ($komunikat_ok): ?>
        <div class="alert alert-success"><?= e($komunikat_ok) ?></div>
    <?php endif; ?>

    <?php if ($komunikat_blad): ?>
        <div class="alert alert-error"><?= e($komunikat_blad) ?></div>
    <?php endif; ?>

    <?php if (!empty($bledy)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($bledy as $blad): ?>
                    <li><?= e($blad) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
