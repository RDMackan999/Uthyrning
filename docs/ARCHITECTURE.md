# Arkitektur

Codex och andra automatiserade kodändrare ska alltid läsa detta dokument innan kodändringar görs.

## Nuläge

Projektets nuvarande fungerande yta är en Codex Sites-landningssida byggd med vinext, Next/React och Tailwind CSS.

Det finns en första PHP-backendgrund från Sprint 1A. Den innehåller endast mappar, Composer-autoloading, config-exempel och tomma Core-klasser. Det finns ingen affärslogik, ingen inloggning, inget API och inga databastabeller.

Det finns inte heller någon aktiv BankID-, Swish- eller Fortnox-integration.

## Nuvarande frontendstruktur

Landningssidan ligger i Sites/vinext-strukturen:

```text
app/
  page.tsx        Startsida och sidsektioner.
  globals.css    Styling för landningssidan.
  layout.tsx     Metadata, språk och global layout.
  chatgpt-auth.ts

public/
  uthyrning-hero.png
  favicon.svg
  file.svg
  globe.svg
  window.svg

build/
  sites-vite-plugin.ts

worker/
  index.ts

.openai/
  hosting.json
```

Bygg- och konfigurationsfiler:

```text
package.json
package-lock.json
vite.config.ts
next.config.ts
postcss.config.mjs
tsconfig.json
eslint.config.mjs
```

Frontendens viktigaste fil är `app/page.tsx`. Den ska inte ersättas, flyttas eller delas upp utan en tydlig uppgift och dokumenterad plan.

## Nuvarande backendstruktur

Sprint 1A lägger till ett passivt PHP-skelett i samma repository. Strukturen är förberedande och ska inte köras som produktionsbackend ännu.

```text
app/
  Core/
  Controllers/
  Middleware/
  Models/
  Repositories/
  Services/
  Helpers/

config/

database/
  migrations/
  seeders/
  schema/

routes/

storage/
  cache/
  logs/
  sessions/
  temp/

tests/
```

`app/page.tsx`, `app/layout.tsx` och `app/globals.css` tillhör fortfarande frontend. PHP-koden under `app/Core/` använder namespace `App\Core` via Composer PSR-4-autoloading. Denna delning av rotkatalogen är tillfällig och ska följas upp innan routing, controllers eller publik PHP-entrypoint byggs.

## Nuvarande databasrelaterade struktur

Följande filer kommer från Sites/vinext-startermallen och är inte en färdig produktdatabas:

```text
db/
drizzle/
drizzle.config.ts
examples/
```

De får användas som referens senare, men riktiga databasbeslut ska följa `docs/DATABASE_PRINCIPLES.md`.

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

Nuvarande ordning:

1. `config/config.example.php` visar appinställningar som namn, miljö, debug, timezone och base URL.
2. `config/database.example.php` visar framtida MySQL/MariaDB-inställningar för lokal utveckling.
3. Riktiga config-filer får inte committas.
4. Bootstrap, PDO-anslutning, felhantering, sessionsinställningar och loggning implementeras i senare sprintar.

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
