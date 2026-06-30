# Arkitektur

Codex och andra automatiserade kodändrare ska alltid läsa detta dokument innan kodändringar görs.

## Nuläge

Projektets nuvarande fungerande yta är en Codex Sites-landningssida byggd med vinext, Next/React och Tailwind CSS.

Viktiga delar:

- `app/`: Next app-kod för landningssidan.
- `public/`: publika statiska filer, inklusive `public/uthyrning-hero.png`.
- `build/`, `worker/` och `.openai/`: Sites/Cloudflare Worker-relaterad bygg- och hostingstruktur.
- `package.json`, `package-lock.json`, `vite.config.ts`, `next.config.ts`, `postcss.config.mjs`, `tsconfig.json`: frontendens byggsetup.

Denna struktur ska bevaras tills en dokumenterad migrering eller samexistens med PHP-strukturen beslutas.

## Målarkitektur

Den långsiktiga plattformen ska byggas som en PHP 8.x-applikation med MySQL eller MariaDB och PDO.

Grundprinciper:

- PHP 8.x utan stort ramverk.
- MySQL/MariaDB med PDO.
- Prepared statements för all databasåtkomst.
- Ingen känslig information i repo.
- Tydlig separation mellan publik frontend, includes, config, API och rollbaserade ytor.
- Externa integrationer byggs först när grundmodeller, säkerhet och rollsystem finns.

## Sites kontra public_html

En framtida PHP-version kan komma att använda `public_html/` som webbrot. Den finns inte som aktiv huvudstruktur i nuläget eftersom den fungerande landningssidan ligger i Sites-strukturen.

När `public_html/` införs ska den planeras ungefär så här:

```text
public_html/
  admin/
  api/
  auth/
  customer/
  renter/
  cms/
  assets/
    config/
    includes/
    scripts/
    styles/
    uploads/
```

Innan filer flyttas från Sites-strukturen till `public_html/` ska README och detta dokument uppdateras med vald strategi.

## PHP-standard

När backend byggs ska PHP-kod följa dessa principer:

- Använd `declare(strict_types=1);` där det är rimligt.
- Använd tydliga fil- och funktionsnamn.
- Separera presentation, validering och databaslogik så långt det går utan stort ramverk.
- Kommentera kod där syftet inte är självklart.
- Hantera fel kontrollerat och läck inte intern information till användaren.
- All databasåtkomst ska gå via PDO.

## Includes och config

När PHP-konfiguration införs ska `config.example.php` visa vilka inställningar som krävs. Riktig `config.php` ska aldrig committas.

Rekommenderad framtida ordning:

1. `config.example.php` visar struktur och exempelvärden.
2. Lokal `config.php` innehåller verkliga värden på servern.
3. En bootstrap/include-fil laddar konfigurationen och skapar PDO-anslutning.
4. Felhantering, sessionsinställningar och loggning initieras centralt.

## API-struktur senare

API ska byggas stegvis under en framtida PHP-struktur, troligen under `public_html/api/`.

API-endpoints ska:

- Validera input strikt.
- Kontrollera session och behörighet.
- Returnera JSON med konsekvent format.
- Använda PDO och prepared statements.
- Logga säkerhetsrelevanta händelser utan att logga hemligheter.

## Separering av ytor

### admin

Administrativa vyer för drift, moderation, systeminställningar och översikt. Ska skyddas med rollbaserad behörighet.

### customer

Kundflöden för den som hyr: profil, bokningar, avtal, betalstatus och historik.

### renter

Uthyrarflöden för den som äger objekt: objekt, priser, tillgänglighet, skick, utlämning, återlämning och intäkter.

### cms

Framtida hantering av statiska sidor, FAQ, villkor och marknadstexter.

## Framtida integrationer

Systemet ska senare kunna stödja BankID, Swish, Fortnox, PWA och flerspråkighet. Dessa integrationer ska inte byggas förrän krav, säkerhet, dataflöden och loggning är dokumenterade.
