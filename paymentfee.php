<?php
// /includes/hooks/mailin_fee.php
if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

use WHMCS\Database\Capsule;
use WHMCS\Billing\Invoice;

/**
 * Kovakoodattu tilisiirtomaksu:
 * - Mail In Payment (system name: 'mailin') => +5,00 €
 * - Muut maksutavat => ei maksulisää
 *
 * Muuta halutessasi alla olevia arvoja.
 */
const MAILIN_GATEWAY_SYSTEM   = 'mailin';          // "Mail In Payment" -moduulin system name
const FEE_DESCRIPTION         = 'Tilisiirtomaksu'; // laskurivin teksti (käytä samaa poistoon)
const FEE_AMOUNT              = 5.00;              // lisämaksu euroissa
const FEE_TAXED               = 0;                 // 1 jos haluat verottaa rivin, muuten 0

/**
 * Lisää/poistaa maksulisän maksutavan perusteella ja päivittää laskun summat.
 */
function applyMailinFee(int $invoiceId, string $gatewaySystem): void
{
    // Poista mahdolliset aiemmat lisämaksurivit (duplikaattien esto ja siivous vaihdoissa).
    Capsule::table('tblinvoiceitems')
        ->where('invoiceid', $invoiceId)
        ->where('description', FEE_DESCRIPTION)
        ->delete();

    // Lisää rivi vain jos maksutapa on Mail In Payment.
    if ($gatewaySystem === MAILIN_GATEWAY_SYSTEM) {
        // Yritetään kohdistaa rivin eräpäivä laskun eräpäivään, muuten nyt.
        $dueDate = Capsule::table('tblinvoices')->where('id', $invoiceId)->value('duedate');

        Capsule::table('tblinvoiceitems')->insert([
            'invoiceid'   => $invoiceId,
            'type'        => 'Item',            // yleinen tyyppi vapaalle riville
            'relid'       => 0,
            'description' => FEE_DESCRIPTION,
            'amount'      => FEE_AMOUNT,
            'taxed'       => FEE_TAXED,
            'duedate'     => $dueDate ?: date('Y-m-d'),
        ]);
    }

    // Päivitä laskun kokonaissummat (ensisijaisesti mallin metodi, varalla API).
    try {
        if ($invoice = Invoice::find($invoiceId)) {
            $invoice->updateInvoiceTotal(); // WHMCS classdocs: Invoice::updateInvoiceTotal()
        }
    } catch (\Throwable $e) {
        try {
            localAPI('UpdateInvoice', ['invoiceid' => $invoiceId]); // pakota uudelleenlaskenta
        } catch (\Throwable $ignored) {}
    }
}

/**
 * 1) Kun lasku luodaan: tarkista sen hetkinen maksutapa.
 * Hook: InvoiceCreation (rivien muokkaus sallittu, WHMCS laskee totalit hookin jälkeen).
 */
add_hook('InvoiceCreation', 1, function (array $vars): void {
    $invoiceId = (int) ($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) return;

    // Nouda laskun maksutapa järjestelmämuodossa (esim. 'mailin', 'paypal', 'banktransfer', ...)
    $gateway = (string) Capsule::table('tblinvoices')->where('id', $invoiceId)->value('paymentmethod');
    applyMailinFee($invoiceId, $gateway);
});

/**
 * 2) Kun laskun maksutapa vaihdetaan: lisää/poista lisämaksu.
 * HUOM: TÄRKEÄ – parametrin nimi on 'paymentmethod' (uusi maksutapa).
 * Lähde: Hook-ref: InvoiceChangeGateway.
 */
add_hook('InvoiceChangeGateway', 1, function (array $vars): void {
    $invoiceId    = (int) ($vars['invoiceid'] ?? 0);
    $newGateway   = (string) ($vars['paymentmethod'] ?? ''); // <-- oikea avain

    if ($invoiceId > 0 && $newGateway !== '') {
        applyMailinFee($invoiceId, $newGateway);
    }
});

// Näytetään Mail In -maksun lisä 5 € jo ostoskorissa / checkoutissa.
add_hook('CartTotalAdjustment', 1, function ($vars) {
    $gateway = $vars['paymentmethod'] ?? '';

    // Näytä lisämaksu vain jos valittu maksutapa on mailin
    if ($gateway === 'mailin') {
        return [
            'description' => 'Tilisiirtomaksu',
            'amount'      => 5.00,
        ];
    }

    // Jos muu maksutapa → ei mitään lisättävää
    return [];
});
