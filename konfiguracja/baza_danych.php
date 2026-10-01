<?php

declare(strict_types=1);

/**
 * Zwraca współdzielone połączenie PDO do bazy danych.
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host    = 'localhost';
        $dbName  = 'booking_system';
        $user    = 'root';
        $pass    = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log('Blad polaczenia z baza danych: ' . $e->getMessage());
            http_response_code(500);
            die('Chwilowa niedostępność serwisu. Spróbuj ponownie za chwilę.');
        }
    }

    return $pdo;
}
