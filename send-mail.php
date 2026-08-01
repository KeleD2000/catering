<?php
/**
 * send-mail.php
 * Fogadja az ajánlatkérő űrlap adatait, és SMTP-n keresztül elküldi
 * a megadott céges e-mail címre a PHPMailer könyvtár segítségével.
 *
 * Feltöltés a tárhelyre: ezt a fájlt a PHPMailer mappával együtt
 * ugyanabba a /mail könyvtárba kell másolni, amit az űrlap
 * fetch('/mail/send-mail.php') hívása céloz.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Csak POST kéréseket fogadunk el
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Csak POST kérés engedélyezett.']);
    exit;
}

// ---------------------------------------------------------
// BEÁLLÍTÁSOK — ezeket kell kitölteni a Rackforestnél kapott
// e-mail fiók / SMTP adatokkal
// ---------------------------------------------------------
const SMTP_HOST       = 'live.smtp.mailtrap.io';
const SMTP_PORT       = 587;
const SMTP_SECURE     = 'tls';
const SMTP_USERNAME   = 'api';
const SMTP_PASSWORD   = 'b317abd66b12db37b93200007d00a41b';    

const MAIL_FROM       = 'api';      // legyen ugyanaz a domain, mint SMTP_USERNAME
const MAIL_FROM_NAME  = 'Elefántos Catering — Weboldal';
const MAIL_TO         = 'k.dominik2000@gmail.com';    // ide érkeznek az ajánlatkérések
const MAIL_TO_NAME    = 'Elefántos Catering';
// ---------------------------------------------------------

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function respond(bool $success, string $message, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function clean(string $value): string
{
    return trim(strip_tags($value));
}

// Honeypot: ha ez a mező ki van töltve, valószínűleg bot küldte
if (!empty($_POST['website'])) {
    // A botnak "sikeresnek" mutatjuk, hogy ne próbálkozzon tovább, de nem küldünk mailt
    respond(true, 'Köszönjük! Az ajánlatkérést elküldtük.');
}

// Kötelező mezők ellenőrzése
$required = ['name', 'phone', 'email', 'event_type'];
foreach ($required as $field) {
    if (empty($_POST[$field]) || trim((string) $_POST[$field]) === '') {
        respond(false, 'Kérjük, töltse ki az összes kötelező mezőt.', 422);
    }
}

$name      = clean((string) $_POST['name']);
$phone     = clean((string) $_POST['phone']);
$email     = clean((string) $_POST['email']);
$eventType = clean((string) $_POST['event_type']);
$guests    = clean((string) ($_POST['guests'] ?? ''));
$location  = clean((string) ($_POST['location'] ?? ''));
$eventDate = clean((string) ($_POST['event_date'] ?? ''));
$eventTime = clean((string) ($_POST['event_time'] ?? ''));
$message   = clean((string) ($_POST['message'] ?? ''));

// E-mail cím validálása
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Kérjük, adjon meg egy érvényes e-mail címet.', 422);
}

// Egyszerű hosszkorlátok a visszaélések ellen
if (mb_strlen($name) > 150 || mb_strlen($message) > 4000) {
    respond(false, 'A megadott adatok túl hosszúak.', 422);
}

$mail = new PHPMailer(true);

try {
    // SMTP beállítások
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_TO, MAIL_TO_NAME);
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Új ajánlatkérés — ' . $eventType . ' (' . $name . ')';

    $rows = [
        'Név'                 => $name,
        'Telefonszám'         => $phone,
        'E-mail'              => $email,
        'Rendezvény típusa'   => $eventType,
        'Vendégek száma'      => $guests !== '' ? $guests : '—',
        'Helyszín'            => $location !== '' ? $location : '—',
        'Rendezvény dátuma'   => $eventDate !== '' ? $eventDate : '—',
        'Kezdés időpontja'    => $eventTime !== '' ? $eventTime : '—',
    ];

    $htmlRows = '';
    foreach ($rows as $label => $value) {
        $htmlRows .= '<tr><td style="padding:6px 12px;color:#888;font-family:Arial,sans-serif;font-size:13px;">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</td><td style="padding:6px 12px;font-family:Arial,sans-serif;font-size:13px;">'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            . '</td></tr>';
    }

    $htmlMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

    $mail->Body = '
        <div style="font-family:Arial,sans-serif;">
            <h2 style="margin-bottom:16px;">Új ajánlatkérés érkezett a weboldalról</h2>
            <table cellpadding="0" cellspacing="0">' . $htmlRows . '</table>
            <h3 style="margin-top:20px;">Üzenet</h3>
            <p>' . ($message !== '' ? $htmlMessage : '—') . '</p>
        </div>
    ';

    $plainRows = '';
    foreach ($rows as $label => $value) {
        $plainRows .= $label . ': ' . $value . "\n";
    }
    $mail->AltBody = "Új ajánlatkérés érkezett a weboldalról\n\n" . $plainRows . "\nÜzenet:\n" . $message;

    $mail->send();

    respond(true, 'Köszönjük! Az ajánlatkérést elküldtük, hamarosan jelentkezünk.');
} catch (PHPMailerException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'mailError' => $mail->ErrorInfo,
        'exception' => $e->getMessage()
    ]);

    exit;
}
