<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../biblioteki/PHPMailer/Exception.php';
require_once __DIR__ . '/../biblioteki/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../biblioteki/PHPMailer/SMTP.php';

// Wysyla jednego maila przez SMTP. Zwraca true jak poszlo, false jak nie.
function wyslij_maila(string $do, string $temat, string $tresc): bool
{
    $poczta = require __DIR__ . '/../konfiguracja/dane_poczty.php';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $poczta['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $poczta['login'];
        $mail->Password   = $poczta['haslo'];
        $mail->SMTPSecure = $poczta['szyfrowanie']; // 'ssl' albo 'tls'
        $mail->Port       = $poczta['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($poczta['login'], 'System rezerwacji');
        $mail->addAddress($do);

        $mail->isHTML(true);
        $mail->Subject = $temat;
        $mail->Body    = $tresc;
        $mail->AltBody = strip_tags($tresc);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // szczegoly bledu tylko do logu, uzytkownik ich nie widzi
        error_log('Blad wysylki maila: ' . $mail->ErrorInfo);
        return false;
    }
}

// Sklada pelny link (z domena), bo w mailu sama sciezka /publiczne/... nie zadziala.
function pelny_adres(string $sciezka): string
{
    $poczta = require __DIR__ . '/../konfiguracja/dane_poczty.php';

    return rtrim($poczta['strona'], '/') . BASE_URL . $sciezka;
}

function wyslij_mail_weryfikacyjny(string $email, string $imie, string $token): bool
{
    $link = pelny_adres('/publiczne/potwierdzenie_email.php?token=' . urlencode($token));

    $tresc = '<p>Cześć ' . htmlspecialchars($imie, ENT_QUOTES, 'UTF-8') . '!</p>'
        . '<p>Dziękujemy za rejestrację. Kliknij w link, żeby aktywować konto:</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
        . '<p>Jeśli to nie Ty zakładałeś konto, po prostu zignoruj tę wiadomość.</p>';

    return wyslij_maila($email, 'Potwierdź swój adres e-mail', $tresc);
}

function wyslij_mail_reset(string $email, string $token): bool
{
    $link = pelny_adres('/publiczne/reset_hasla.php?token=' . urlencode($token));

    $tresc = '<p>Dostaliśmy prośbę o zresetowanie hasła do Twojego konta.</p>'
        . '<p>Kliknij w link, żeby ustawić nowe hasło (ważny przez 1 godzinę):</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
        . '<p>Jeśli to nie Ty o to prosiłeś, zignoruj tę wiadomość - Twoje hasło się nie zmieni.</p>';

    return wyslij_maila($email, 'Reset hasła', $tresc);
}
