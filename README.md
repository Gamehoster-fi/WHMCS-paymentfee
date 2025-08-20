# Lisämaksu Mail In Payment (Tilisiirto) -maksutavalle WHMCS:ssä

Tämä WHMCS-hook lisää automaattisesti 5 € lisämaksun laskuun, kun asiakkaan maksutapana käytetään Mail In Payment (tilisiirto). Hook toimii sekä laskun luonnin yhteydessä että maksutavan vaihtuessa.

---

## Ominaisuudet

- Lisää 5 € lisämaksun automaattisesti, kun maksutapa on mailin.
- Poistaa lisämaksun, jos maksutapa vaihdetaan toiseen.
- Päivittää laskun kokonaissummat automaattisesti.
- Veroton lisämaksu (muutettavissa koodista).

---

## Asennus

1. Lataa tai kopioi `Paymentfee.php` tiedosto `includes/hooks/` kansioon WHMCS-asennuksessasi.
2. Tyhjennä template cache WHMCS:stä: Utilities → System → System Cleanup → Empty Template Cache
3. Hook aktivoituu automaattisesti, kun tiedosto on `hooks`-kansiossa.
4. Muokkaa tarvittaessa seuraavia arvoja koodissa:

```php
const MAILIN_GATEWAY_SYSTEM   = 'mailin';          // Maksutavan system name
const FEE_DESCRIPTION         = 'Tilisiirtomaksu'; // Laskurivin kuvaus
const FEE_AMOUNT              = 5.00;              // Lisämaksu euroissa
const FEE_TAXED               = 0;                 // 1 jos verotetaan, 0 jos ei```
