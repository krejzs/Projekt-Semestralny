<?php
// Gorna czesc kazdej strony panelu administratora: <head>, menu i komunikaty.
// Strona, ktora to dolacza, musi wczesniej ustawic zmienna $tytul.
// Moze tez ustawic $bledy (lista bledow) i $aktywne_menu (ktory link podswietlic).

$menu = [
    'panel.php'       => 'Pulpit',
    'jezyki.php'      => 'Języki',
    'lekcje.php'      => 'Lekcje',
    'lektorzy.php'    => 'Lektorzy',
    'rezerwacje.php'  => 'Zapisy na lekcje',
    'uzytkownicy.php' => 'Uczniowie i konta',
];

$aktywne_menu   = $aktywne_menu ?? basename($_SERVER['SCRIPT_NAME']);
$komunikat_ok   = flash('success');
$komunikat_blad = flash('error');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tytul) ?> - Szkoła językowa</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/zasoby/styl.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/zasoby/styl_admin.css">
</head>
<body class="admin">

<div class="admin-uklad">

    <nav class="admin-menu">
        <div class="admin-logo">
            Szkoła językowa
            <small>panel administratora</small>
        </div>

        <?php foreach ($menu as $plik => $nazwa): ?>
            <a href="<?= BASE_URL ?>/administrator/<?= $plik ?>"
               class="<?= $plik === $aktywne_menu ? 'aktywny' : '' ?>"><?= e($nazwa) ?></a>
        <?php endforeach; ?>

        <hr>
        <a href="<?= BASE_URL ?>/publiczne/profil.php">Mój profil</a>
        <a href="<?= BASE_URL ?>/publiczne/wylogowanie.php">Wyloguj się</a>
    </nav>

    <main class="admin-tresc">
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
