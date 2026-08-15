<?php
/**
 * Brevo (Sendinblue) beállítások
 * ------------------------------------------------------------
 * 1) Lépj be: https://app.brevo.com/settings/keys/api
 * 2) Generálj egy új API kulcsot (nem az SMTP kulcsot, hanem az "API Keys" fület!)
 * 3) Illeszd be alább a BREVO_API_KEY értékét
 * 4) A FROM_EMAIL-nek egy Brevo-ban HITELESÍTETT (verified sender) domainről/címről
 *    kell lennie, különben a Brevo elutasítja a küldést.
 *
 * FONTOS: ezt a fájlt NE tedd nyilvános git repóba (add hozzá a .gitignore-hoz),
 * és győződj meg róla, hogy a szerveren a config.php nem tölthető le böngészőből
 * (a legtöbb Apache/PHP hosting alapból nem futtatja le .php-ként, ha közvetlenül
 * elérik — de érdemes egy .htaccess-szel is védeni, lásd lent).
 */

return [
    // Brevo API kulcs (Transactional > SMTP & API > API Keys)
    'BREVO_API_KEY' => 'IDE_MASOLD_A_BREVO_API_KULCSOT',

    // Ez a cím jelenik meg feladóként — Brevo-ban hitelesítve kell legyen
    'FROM_EMAIL'    => 'noreply@peldacegdomain.hu',
    'FROM_NAME'     => 'Weboldal Ajánlatkérő Űrlap',

    // Ide érkeznek az ajánlatkérések
    'TO_EMAIL'      => 'info@peldacegdomain.hu',
    'TO_NAME'       => 'Catering Csapat',

    // Ha szeretnél automatikus visszaigazoló emailt is küldeni a jelentkezőnek
    'SEND_CONFIRMATION_TO_USER' => true,
];
