# Arkitektur

Codex och andra automatiserade kodändrare ska alltid läsa detta dokument innan kodändringar görs.

## Nuläge

Projektets nuvarande fungerande yta är en Codex Sites-landningssida byggd med vinext, Next/React och Tailwind CSS.

Det finns en första PHP-backendkärna från Sprint 1B. Den innehåller config-laddning, bootstrap, enkel routing, request/response, filbaserad loggning och enkel felhantering. Sprint 1C lägger till en lazy-loaded PDO-databasgrund. Det finns fortfarande ingen affärslogik, ingen inloggning, inget API, inga databastabeller och inga migrationer.

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

Sprint 1B implementerar ett litet PHP-kärnlager i samma repository. Sprint 1C utökar kärnan med databasanslutningsgrund, men strukturen är fortfarande infrastruktur och ska inte betraktas som färdig produktionsbackend.

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

`app/page.tsx`, `app/layout.tsx` och `app/globals.css` tillhör fortfarande frontend. PHP-koden under `app/Core/` använder namespace `App\Core` via Composer PSR-4-autoloading. Denna delning av rotkatalogen är tillfällig och ska följas upp innan publik PHP-entrypoint byggs.

### Core-lager

Nuvarande Core-lager ansvarar endast för infrastruktur:

- `Bootstrap`: laddar config, sätter timezone, registrerar felhantering, laddar routes och dispatchar request.
- `Config`: läser `config/config.php` och `config/database.php` om de finns, annars respektive `.example.php`, och exponerar värden via dot notation.
- `Router`: stödjer exakta `GET`- och `POST`-routes via `add()`, `get()`, `post()` och `dispatch()`.
- `Request`: läser metod, URI, querystring och POST-data.
- `Response`: skapar text-, HTML- och JSON-responser med statuskod och headers.
- `Logger`: skriver filbaserade loggar till `storage/logs/` och maskerar kända känsliga nycklar.
- `ErrorHandler`: registrerar PHP error/exception handlers och visar detaljer endast i development/debug.
- `Database`: facade för framtida databasåtkomst via lazy `DatabaseConnection`.
- `DatabaseConnection`: förbereder PDO-anslutning mot MySQL/MariaDB först när `pdo()` efterfrågas.
- `QueryBuilder`: tom placeholder för framtida query builder och innehåller ingen SQL-logik i Sprint 1C.

### Tekniska routes

`routes/web.php` innehåller endast tekniska routes:

```text
GET /       Backend initialized
GET /health JSON health check
```

Dessa routes är inte ett publikt API, innehåller ingen affärslogik och kräver ingen databasanslutning.

## Nuvarande databasrelaterade struktur

Sprint 1C har en PHP-baserad databasgrund:

```text
app/Core/Database.php
app/Core/DatabaseConnection.php
app/Core/QueryBuilder.php
config/database.example.php
```

Databasanslutningen är lazy-loaded och skapas inte av Bootstrap eller health check. Backend ska kunna starta även om lokal databas saknas, så länge ingen databasfunktion används.

Följande filer kommer från Sites/vinext-startermallen och är inte en färdig produktdatabas:

```text
db/
drizzle/
drizzle.config.ts
examples/
```

De får användas som referens senare, men riktiga databasbeslut ska följa `docs/DATABASE_PRINCIPLES.md`, `docs/DATABASE_NAMING_STANDARD.md` och `docs/DATABASE_DESIGN.md`.

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

När PHP-konfiguration införs ska exempelkonfiguration visa vilka inställningar som krävs. Riktiga config-filer ska aldrig committas.

Nuvarande ordning:

1. `config/config.php` används om den finns lokalt, annars `config/config.example.php`.
2. `config/database.php` används om den finns lokalt, annars `config/database.example.php`.
3. `Config::get()` exponerar både `app.*` och `database.*` via dot notation.
4. Riktiga config-filer får inte committas.
5. PDO-anslutning sker först när `Database::pdo()` eller `Database::connection()->pdo()` används.
6. Sessionsinställningar och databasberoende affärslogik implementeras i senare sprintar.

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
