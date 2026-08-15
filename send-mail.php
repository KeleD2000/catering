<?php
/**
 * send-mail.php
 * ------------------------------------------------------------
 * Fogadja a cateringForm AJAX POST kérését, és a Brevo (Sendinblue)
 * Transactional Email API-ján keresztül elküldi emailben.
 *
 * A frontend (script.js) egy JSON választ vár:
 *   { "success": true/false, "message": "..." }
 */

header('Content-Type: application/json; charset=utf-8');

// Csak POST kérést fogadunk el
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Csak POST kérés engedélyezett.']);
    exit;
}

$config = require __DIR__ . '/config.php';

if (empty($config['BREVO_API_KEY']) || $config['BREVO_API_KEY'] === 'IDE_MASOLD_A_BREVO_API_KULCSOT') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A szerver e-mail küldése nincs beállítva. Kérjük, keressen minket telefonon.']);
    exit;
}

// ------------------------------------------------------------
// 1) Bemenet beolvasása és tisztítása
// ------------------------------------------------------------
function clean($value) {
    return trim(strip_tags((string) ($value ?? '')));
}

$name       = clean($_POST['name'] ?? '');
$phone      = clean($_POST['phone'] ?? '');
$email      = clean($_POST['email'] ?? '');
$eventType  = clean($_POST['event_type'] ?? '');
$guests     = clean($_POST['guests'] ?? '');
$location   = clean($_POST['location'] ?? '');
$eventDate  = clean($_POST['event_date'] ?? '');
$eventTime  = clean($_POST['event_time'] ?? '');
$message    = clean($_POST['message'] ?? '');

// Egyszerű "honeypot" spam elleni védelem (opcionális — ha van rejtett mező a formon "website" néven)
if (!empty($_POST['website'] ?? '')) {
    // Botnak tűnik: hamisan sikeresnek jelezzük vissza, de nem küldünk semmit
    echo json_encode(['success' => true, 'message' => 'Köszönjük! Az ajánlatkérést elküldtük, hamarosan jelentkezünk.']);
    exit;
}

// ------------------------------------------------------------
// 2) Szerveroldali validáció
// ------------------------------------------------------------
$errors = [];

if ($name === '') {
    $errors[] = 'A név megadása kötelező.';
}
if ($phone === '') {
    $errors[] = 'A telefonszám megadása kötelező.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Érvényes e-mail cím megadása kötelező.';
}
if ($eventType === '') {
    $errors[] = 'A rendezvény típusának megadása kötelező.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ------------------------------------------------------------
// 3) Email tartalom összeállítása
// ------------------------------------------------------------
function row($label, $value) {
    if ($value === '') return '';
    return '<tr><td style="padding:6px 12px;color:#666;font-weight:600;white-space:nowrap;">'
        . htmlspecialchars($label) . '</td><td style="padding:6px 12px;">'
        . nl2br(htmlspecialchars($value)) . '</td></tr>';
}

$htmlBody = '
<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;">
  <h2 style="color:#2b2b2b;">Új ajánlatkérés érkezett a weboldalról</h2>
  <table style="border-collapse:collapse;width:100%;">
    ' . row('Név', $name) . '
    ' . row('Telefonszám', $phone) . '
    ' . row('E-mail', $email) . '
    ' . row('Rendezvény típusa', $eventType) . '
    ' . row('Vendégek száma', $guests) . '
    ' . row('Helyszín', $location) . '
    ' . row('Dátum', $eventDate) . '
    ' . row('Időpont', $eventTime) . '
    ' . row('Üzenet', $message) . '
  </table>
</div>';

$textBody = "Új ajánlatkérés érkezett a weboldalról\n\n"
    . "Név: $name\nTelefonszám: $phone\nE-mail: $email\n"
    . "Rendezvény típusa: $eventType\nVendégek száma: $guests\n"
    . "Helyszín: $location\nDátum: $eventDate\nIdőpont: $eventTime\n"
    . "Üzenet: $message\n";

// ------------------------------------------------------------
// 4) Küldés a Brevo API-n keresztül
// ------------------------------------------------------------
function sendBrevoEmail($apiKey, $payload) {
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response   = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    return [
        'ok'       => ($httpCode >= 200 && $httpCode < 300),
        'httpCode' => $httpCode,
        'body'     => $response,
        'error'    => $curlError,
    ];
}

// 4a) Belső értesítő email (a cégnek)
$notifyPayload = [
    'sender'      => ['name' => $config['FROM_NAME'], 'email' => $config['FROM_EMAIL']],
    'to'          => [['name' => $config['TO_NAME'], 'email' => $config['TO_EMAIL']]],
    'replyTo'     => ['name' => $name, 'email' => $email],
    'subject'     => "Új ajánlatkérés – $eventType ($name)",
    'htmlContent' => $htmlBody,
    'textContent' => $textBody,
];

$result = sendBrevoEmail($config['BREVO_API_KEY'], $notifyPayload);

if (!$result['ok']) {
    error_log('Brevo email küldési hiba: HTTP ' . $result['httpCode'] . ' – ' . $result['body'] . ' – curl: ' . $result['error']);
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Hiba történt a küldés során. Kérjük, próbálja újra, vagy hívjon minket telefonon.']);
    exit;
}

// 4b) (Opcionális) Visszaigazoló email a jelentkezőnek
if (!empty($config['SEND_CONFIRMATION_TO_USER'])) {
    $confirmPayload = [
        'sender'      => ['name' => $config['FROM_NAME'], 'email' => $config['FROM_EMAIL']],
        'to'          => [['name' => $name, 'email' => $email]],
        'subject'     => 'Köszönjük az ajánlatkérést!',
        'htmlContent' => '<div style="font-family:Arial,Helvetica,sans-serif;">'
            . '<p>Kedves ' . htmlspecialchars($name) . '!</p>'
            . '<p>Köszönjük az ajánlatkérést, hamarosan felvesszük Önnel a kapcsolatot.</p>'
            . '<p>Üdvözlettel,<br>' . htmlspecialchars($config['TO_NAME']) . '</p>'
            . '</div>',
        'textContent' => "Kedves $name!\n\nKöszönjük az ajánlatkérést, hamarosan felvesszük Önnel a kapcsolatot.\n\nÜdvözlettel,\n{$config['TO_NAME']}",
    ];
    // Ha ez elbukik, ne buktassuk el az egész kérést — csak logoljuk
    $confirmResult = sendBrevoEmail($config['BREVO_API_KEY'], $confirmPayload);
    if (!$confirmResult['ok']) {
        error_log('Brevo visszaigazoló email hiba: HTTP ' . $confirmResult['httpCode'] . ' – ' . $confirmResult['body']);
    }
}

// ------------------------------------------------------------
// 5) Siker
// ------------------------------------------------------------
echo json_encode([
    'success' => true,
    'message' => 'Köszönjük! Az ajánlatkérést elküldtük, hamarosan jelentkezünk.',
]);
