<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

// =====================================================
// BEÁLLÍTÁSOK – ezt a 3 sort kell átírni
// =====================================================
$to      = 'k.dominik2000@gmail.com';      // ide érkeznek az ajánlatkérések
$from    = 'noreply@elefantoscatering.hu';    // FONTOS: a Rackhoston lévő domain egyik címe legyen!
$siteUrl = 'https://elefantoscatering.hu';    // az oldal címe
$sendConfirmation = true;                    // true = az ügyfél is kap visszaigazoló levelet
// =====================================================

// Védelem: amíg a fenti 3 beállítás nincs kitöltve, ne jelezzen "sikeres" küldést
$configMissing = (strpos($to, 'IDE_AZ_') !== false)
    || (strpos($from, 'ELEFANTOS-DOMAIN') !== false)
    || (strpos($siteUrl, 'ELEFANTOS-DOMAIN') !== false);

// Üzenetek nyelvenként (a script.js ezeket jeleníti meg)
$MSG = [
    'hu' => [
        'ok'      => 'Köszönjük! Az ajánlatkérést elküldtük, hamarosan jelentkezünk.',
        'invalid' => 'Kérjük, töltse ki az összes mezőt, és adjon meg érvényes adatokat.',
        'fail'    => 'Hiba történt a küldés során. Kérjük, próbálja újra, vagy hívjon minket telefonon.',
        'method'  => 'Érvénytelen kérés.',
    ],
    'en' => [
        'ok'      => 'Thank you! Your enquiry has been sent, we will get back to you shortly.',
        'invalid' => 'Please fill in all fields with valid data.',
        'fail'    => 'Something went wrong while sending. Please try again or call us.',
        'method'  => 'Invalid request.',
    ],
    'de' => [
        'ok'      => 'Vielen Dank! Ihre Anfrage wurde gesendet, wir melden uns in Kürze bei Ihnen.',
        'invalid' => 'Bitte füllen Sie alle Felder mit gültigen Angaben aus.',
        'fail'    => 'Beim Senden ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut oder rufen Sie uns an.',
        'method'  => 'Ungültige Anfrage.',
    ],
];

function respond($code, $success, $message) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function clean($v, $max = 200) {
    $v = trim(str_replace(["\r", "\n"], ' ', (string)$v));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

function clean_multiline($v, $max = 3000) {
    $v = trim(str_replace("\r", '', (string)$v));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

function h($v) {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// Nyelv
$lang = $_POST['lang'] ?? 'hu';
if (!isset($MSG[$lang])) $lang = 'hu';
$M = $MSG[$lang];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, $M['method']);
}

if ($configMissing) {
    error_log('send-mail.php: a $to / $from / $siteUrl beállítások még nincsenek kitöltve.');
    respond(500, false, $M['fail']);
}

// Honeypot: ha kitöltötték, bot volt – csendben "sikeresnek" tűnünk
if (!empty($_POST['website'])) {
    respond(200, true, $M['ok']);
}

// Mezők
$name      = clean($_POST['name'] ?? '');
$phone     = clean($_POST['phone'] ?? '');
$email     = clean($_POST['email'] ?? '');
$eventType = clean($_POST['event_type'] ?? '');
$guests    = clean($_POST['guests'] ?? '', 10);
$location  = clean($_POST['location'] ?? '');
$eventDate = clean($_POST['event_date'] ?? '', 20);
$eventTime = clean($_POST['event_time'] ?? '', 10);
$message   = clean_multiline($_POST['message'] ?? '');

// Validálás
$guestsOk = ($guests !== '' && ctype_digit($guests) && (int)$guests > 0);
$dateOk   = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate);
$timeOk   = (bool)preg_match('/^\d{2}:\d{2}$/', $eventTime);
$typeOk   = in_array($eventType, ['Grill', 'Pizzás rendezvény', 'Bankett vacsora', 'Hideg büfé', 'Céges rendezvény', 'Esküvő'], true);

if ($name === '' || $phone === '' || $location === '' || $message === ''
    || !$typeOk || !$guestsOk || !$dateOk || !$timeOk
    || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, false, $M['invalid']);
}
if ($guests !== '' && !ctype_digit($guests)) $guests = '';
if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) $eventDate = '';
if ($eventTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $eventTime)) $eventTime = '';

$langNames = ['hu' => 'magyar', 'en' => 'angol', 'de' => 'német'];
$when      = date('Y. m. d. H:i');
$telHref   = 'tel:' . preg_replace('/[^\d+]/', '', $phone);

$subject = '=?UTF-8?B?' . base64_encode('Új ajánlatkérés: ' . $name . ' – ' . $eventType) . '?=';

// ---------- Sima szöveges változat ----------
$text  = "Új ajánlatkérés érkezett a weboldalról\n\n";
$text .= "Név: $name\n";
$text .= "Telefon: $phone\n";
$text .= "E-mail: $email\n";
$text .= "Rendezvény típusa: $eventType\n";
if ($guests !== '')    $text .= "Vendégek száma: $guests\n";
if ($location !== '')  $text .= "Helyszín: $location\n";
if ($eventDate !== '') $text .= "Dátum: $eventDate\n";
if ($eventTime !== '') $text .= "Kezdés: $eventTime\n";
if ($message !== '')   $text .= "\nÜzenet:\n$message\n";
$text .= "\nÜgyfél nyelve: " . $langNames[$lang] . "\n";
$text .= "Beérkezett: $when\n";

// ---------- HTML változat ----------
function row($label, $valueHtml) {
    return '<tr><td style="padding:14px 0;border-bottom:1px solid #E3D9BF;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:0.06em;text-transform:uppercase;color:#786F52;margin-bottom:4px;">' . h($label) . '</div>'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.4;color:#241F14;">' . $valueHtml . '</div>'
        . '</td></tr>';
}

$linkStyle = 'color:#A97F31;text-decoration:underline;';

$rows  = row('Név', h($name));
$rows .= row('Telefon', '<a href="' . h($telHref) . '" style="' . $linkStyle . '">' . h($phone) . '</a>');
$rows .= row('E-mail', '<a href="mailto:' . h($email) . '" style="' . $linkStyle . '">' . h($email) . '</a>');
$rows .= row('Rendezvény típusa', '<span style="display:inline-block;background:#F5EFE0;border:1px solid #E3D9BF;border-radius:999px;padding:5px 14px;font-size:14px;">' . h($eventType) . '</span>');
if ($guests !== '')    $rows .= row('Vendégek száma', h($guests) . ' fő');
if ($location !== '')  $rows .= row('Helyszín', h($location));
if ($eventDate !== '' || $eventTime !== '') {
    $rows .= row('Időpont', h(trim($eventDate . ' ' . $eventTime)));
}
if ($message !== '')   $rows .= row('Üzenet', nl2br(h($message)));
$rows .= row('Ügyfél nyelve', h($langNames[$lang]));

$html = '<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Új ajánlatkérés</title>
</head>
<body style="margin:0;padding:0;background:#FAF7F0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#FAF7F0" style="background:#FAF7F0;">
<tr><td align="center" style="padding:28px 12px;">

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">

    <tr><td bgcolor="#141C40" style="background:#141C40;padding:26px 28px;border-radius:4px 4px 0 0;">
      <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:24px;line-height:1.1;color:#C9A25C;letter-spacing:0.05em;">Exclusive Catering</div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:0.15em;text-transform:uppercase;color:#9AA3CF;margin-top:6px;">by Elefántos</div>
    </td></tr>

    <tr><td height="4" bgcolor="#C9A25C" style="background:#C9A25C;font-size:0;line-height:0;">&nbsp;</td></tr>

    <tr><td bgcolor="#FFFFFF" style="background:#FFFFFF;padding:30px 28px 10px;border-left:1px solid #E3D9BF;border-right:1px solid #E3D9BF;">
      <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:22px;line-height:1.25;color:#241F14;">Új ajánlatkérés érkezett</div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#786F52;margin-top:6px;">A weboldal ajánlatkérő űrlapján keresztül.</div>
    </td></tr>

    <tr><td bgcolor="#FFFFFF" style="background:#FFFFFF;padding:10px 28px 6px;border-left:1px solid #E3D9BF;border-right:1px solid #E3D9BF;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        ' . $rows . '
      </table>
    </td></tr>

    <tr><td bgcolor="#FFFFFF" align="left" style="background:#FFFFFF;padding:22px 28px 32px;border-left:1px solid #E3D9BF;border-right:1px solid #E3D9BF;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td bgcolor="#A97F31" style="background:#A97F31;border-radius:2px;">
          <a href="mailto:' . h($email) . '" style="display:inline-block;padding:13px 26px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#FFFDF8;text-decoration:none;">Válasz az érdeklődőnek</a>
        </td>
      </tr></table>
    </td></tr>

    <tr><td bgcolor="#EEE6D3" style="background:#EEE6D3;padding:16px 28px;border:1px solid #E3D9BF;border-top:none;border-radius:0 0 4px 4px;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#786F52;">
        Beérkezett: ' . h($when) . '<br>
        Ez az üzenet a <a href="' . h($siteUrl) . '" style="color:#786F52;">' . h($siteUrl) . '</a> weboldalról érkezett.
      </div>
    </td></tr>

  </table>

</td></tr>
</table>
</body>
</html>';

// ---------- Összeállítás (szöveg + HTML) ----------
$boundary = 'b' . md5(uniqid('', true));

$headers  = "From: Exclusive Catering by Elefantos <$from>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

$body  = "--$boundary\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
$body .= chunk_split(base64_encode($text)) . "\r\n";
$body .= "--$boundary\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
$body .= chunk_split(base64_encode($html)) . "\r\n";
$body .= "--$boundary--";

$sent = mail($to, $subject, $body, $headers, '-f' . $from);

if (!$sent) {
    respond(500, false, $M['fail']);
}

// ---------- Visszaigazoló levél az ügyfélnek (a saját nyelvén) ----------
if ($sendConfirmation) {
    $typeMap = [
        'en' => ['Grill' => 'Barbecue', 'Pizzás rendezvény' => 'Pizza event', 'Bankett vacsora' => 'Banquet dinner',
            'Hideg büfé' => 'Cold buffet', 'Céges rendezvény' => 'Corporate event', 'Esküvő' => 'Wedding'],
        'de' => ['Grill' => 'Grill', 'Pizzás rendezvény' => 'Pizza-Event', 'Bankett vacsora' => 'Bankettessen',
            'Hideg büfé' => 'Kaltes Buffet', 'Céges rendezvény' => 'Firmenveranstaltung', 'Esküvő' => 'Hochzeit'],
    ];
    $typeShown = $typeMap[$lang][$eventType] ?? $eventType;

    $C = [
        'hu' => [
            'subject' => 'Megkaptuk ajánlatkérését – Exclusive Catering by Elefántos',
            'title'   => 'Köszönjük az ajánlatkérést!',
            'intro'   => 'Kedves {name}! Megkaptuk ajánlatkérését, munkatársunk rövid időn belül felveszi Önnel a kapcsolatot.',
            'sumH'    => 'Az Ön által megadott adatok',
            'L'       => ['type' => 'Rendezvény típusa', 'guests' => 'Vendégek száma', 'loc' => 'Helyszín', 'date' => 'Időpont', 'msg' => 'Üzenet'],
            'guestsU' => ' fő',
            'urgent'  => 'Sürgős esetben hívjon minket:',
            'phone'   => '06 30 316 8429',
            'sign'    => 'Üdvözlettel,<br>Exclusive Catering by Elefántos',
            'auto'    => 'Ez egy automatikus visszaigazolás. Kérdés esetén válaszoljon erre a levélre.',
        ],
        'en' => [
            'subject' => 'We received your enquiry – Exclusive Catering by Elefántos',
            'title'   => 'Thank you for your enquiry!',
            'intro'   => 'Dear {name}, we have received your enquiry and our team will contact you shortly.',
            'sumH'    => 'Your details',
            'L'       => ['type' => 'Type of event', 'guests' => 'Number of guests', 'loc' => 'Venue', 'date' => 'Date and time', 'msg' => 'Message'],
            'guestsU' => ' guests',
            'urgent'  => 'In urgent cases please call us:',
            'phone'   => '+36 30 316 8429',
            'sign'    => 'Kind regards,<br>Exclusive Catering by Elefántos',
            'auto'    => 'This is an automatic confirmation. If you have questions, simply reply to this e-mail.',
        ],
        'de' => [
            'subject' => 'Wir haben Ihre Anfrage erhalten – Exclusive Catering by Elefántos',
            'title'   => 'Vielen Dank für Ihre Anfrage!',
            'intro'   => 'Guten Tag {name}, wir haben Ihre Anfrage erhalten und melden uns in Kürze bei Ihnen.',
            'sumH'    => 'Ihre Angaben',
            'L'       => ['type' => 'Art der Veranstaltung', 'guests' => 'Anzahl der Gäste', 'loc' => 'Veranstaltungsort', 'date' => 'Datum und Uhrzeit', 'msg' => 'Nachricht'],
            'guestsU' => ' Gäste',
            'urgent'  => 'In dringenden Fällen rufen Sie uns an:',
            'phone'   => '+36 30 316 8429',
            'sign'    => 'Mit freundlichen Grüßen<br>Exclusive Catering by Elefántos',
            'auto'    => 'Dies ist eine automatische Bestätigung. Bei Fragen antworten Sie einfach auf diese E-Mail.',
        ],
    ][$lang];

    $cRows = row($C['L']['type'], h($typeShown));
    if ($guests !== '')    $cRows .= row($C['L']['guests'], h($guests) . h($C['guestsU']));
    if ($location !== '')  $cRows .= row($C['L']['loc'], h($location));
    if ($eventDate !== '' || $eventTime !== '') $cRows .= row($C['L']['date'], h(trim($eventDate . ' ' . $eventTime)));
    if ($message !== '')   $cRows .= row($C['L']['msg'], nl2br(h($message)));

    $intro = str_replace('{name}', h($name), h($C['intro']));
    $introHtml = str_replace(h('{name}'), h($name), $intro);

    $cHtml = '<!DOCTYPE html>
<html lang="' . h($lang) . '">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($C['title']) . '</title></head>
<body style="margin:0;padding:0;background:#FAF7F0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#FAF7F0" style="background:#FAF7F0;">
<tr><td align="center" style="padding:28px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
    <tr><td bgcolor="#141C40" style="background:#141C40;padding:26px 28px;border-radius:4px 4px 0 0;">
      <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:24px;line-height:1.1;color:#C9A25C;letter-spacing:0.05em;">Exclusive Catering</div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:0.15em;text-transform:uppercase;color:#9AA3CF;margin-top:6px;">by Elefántos</div>
    </td></tr>
    <tr><td height="4" bgcolor="#C9A25C" style="background:#C9A25C;font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td bgcolor="#FFFFFF" style="background:#FFFFFF;padding:30px 28px 10px;border-left:1px solid #E3D9BF;border-right:1px solid #E3D9BF;">
      <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:22px;line-height:1.25;color:#241F14;">' . h($C['title']) . '</div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#4F4A38;margin-top:10px;">' . $introHtml . '</div>
    </td></tr>
    <tr><td bgcolor="#FFFFFF" style="background:#FFFFFF;padding:18px 28px 0;border-left:1px solid #E3D9BF;border-right:1px solid #E3D9BF;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#A97F31;">' . h($C['sumH']) . '</div>
    </td></tr>
    <tr><td bgcolor="#FFFFFF" style="background:#FFFFFF;padding:4px 28px 6px;border-left:1px solid #E3D9BF;border-right:1px solid #E3D9BF;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $cRows . '</table>
    </td></tr>
    <tr><td bgcolor="#FFFFFF" style="background:#FFFFFF;padding:20px 28px 30px;border-left:1px solid #E3D9BF;border-right:1px solid #E3D9BF;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#4F4A38;">' . h($C['urgent']) . ' <a href="tel:+36303168429" style="color:#A97F31;font-weight:bold;text-decoration:none;">' . h($C['phone']) . '</a></div>
      <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:15px;line-height:1.6;color:#241F14;margin-top:18px;">' . $C['sign'] . '</div>
    </td></tr>
    <tr><td bgcolor="#EEE6D3" style="background:#EEE6D3;padding:16px 28px;border:1px solid #E3D9BF;border-top:none;border-radius:0 0 4px 4px;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#786F52;">' . h($C['auto']) . '<br>7621 Pécs, Jókai tér 6. · <a href="' . h($siteUrl) . '" style="color:#786F52;">' . h($siteUrl) . '</a></div>
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>';

    $cText  = str_replace('{name}', $name, $C['intro']) . "\n\n";
    $cText .= $C['sumH'] . ":\n- " . $C['L']['type'] . ': ' . $typeShown . "\n";
    if ($guests !== '')    $cText .= '- ' . $C['L']['guests'] . ': ' . $guests . "\n";
    if ($location !== '')  $cText .= '- ' . $C['L']['loc'] . ': ' . $location . "\n";
    if ($eventDate !== '' || $eventTime !== '') $cText .= '- ' . $C['L']['date'] . ': ' . trim($eventDate . ' ' . $eventTime) . "\n";
    if ($message !== '')   $cText .= '- ' . $C['L']['msg'] . ': ' . $message . "\n";
    $cText .= "\n" . $C['urgent'] . ' ' . $C['phone'] . "\n\n" . strip_tags(str_replace('<br>', "\n", $C['sign'])) . "\n";

    $cBoundary = 'c' . md5(uniqid('', true));
    $cHeaders  = "From: Exclusive Catering by Elefantos <$from>\r\n";
    $cHeaders .= "Reply-To: $to\r\n";
    $cHeaders .= "MIME-Version: 1.0\r\n";
    $cHeaders .= "Content-Type: multipart/alternative; boundary=\"$cBoundary\"\r\n";

    $cBody  = "--$cBoundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $cBody .= chunk_split(base64_encode($cText)) . "\r\n";
    $cBody .= "--$cBoundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $cBody .= chunk_split(base64_encode($cHtml)) . "\r\n";
    $cBody .= "--$cBoundary--";

    $cSubject = '=?UTF-8?B?' . base64_encode($C['subject']) . '?=';

    // Ha ez nem sikerül, az ajánlatkérés akkor is megérkezett hozzátok – nem jelzünk hibát
    @mail($email, $cSubject, $cBody, $cHeaders, '-f' . $from);
}

respond(200, true, $M['ok']);