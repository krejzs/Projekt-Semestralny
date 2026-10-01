<?php

declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

define('SESSION_LIFETIME', 60 * 60 * 2); // 2 godziny bezczynności - wylogowanie
define('MAX_LOGIN_ATTEMPTS', 5);         // liczba prób przed zablokowaniem konta
define('LOCKOUT_MINUTES', 15);           // czas blokady konta po przekroczeniu limitu

require_once __DIR__ . '/baza_danych.php';
require_once __DIR__ . '/../pomoce/funkcje_pomocnicze.php';
require_once __DIR__ . '/../pomoce/ochrona_csrf.php';
require_once __DIR__ . '/../pomoce/uwierzytelnianie.php';

// Automatyczne wygaszanie sesji po okresie bezczynności
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();
