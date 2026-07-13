# Uthyrning

Uthyrning är en svensk uthyrningsplattform för verktyg, maskiner, släp och utrustning.

Det här GitHub-repot är projektets huvudkälla. Den nuvarande fungerande landningssidan är överflyttad från Codex Sites och behålls i sin befintliga Sites/vinext-struktur tills PHP/MySQL-backend byggs stegvis.

## Nuvarande status

- Fungerande landningssida: ja.
- Backend: Sprint 1B-kärna finns för config, routing, request/response, logging och felhantering.
- Databasgrund: Sprint 1C lägger till lazy PDO-anslutning via `App\Core\Database`.
- Migrationsgrund: Sprint 1D lägger till en enkel migrationsmotor och intern `migrations`-tabell, men inga produkttabeller.
- Modellgrund: Sprint 1E lägger till BaseModel, BaseRepository, Collection och ModelException utan SQL eller affärslogik.
- Controller-/view-grund: Sprint 1F lägger till BaseController, View, RedirectResponse och HomeController utan affärslogik.
- HTTP-grund: Sprint 1G lägger till JsonResponse, ViewResponse, HttpException och NotFoundException utan affärslogik.
- Lokal adminstart: Sprint 2H lägger till ett CLI-verktyg för att skapa första administratören i lokal databas.
- Lokal PHP-körning: Sprint 2I lägger till `public/index.php` som PHP-entrypoint och `database/seed.php` för idempotent seedning.
- BankID, Swish och Fortnox: endast förberedda i text och planering, inte integrerade.

## Var landningssidan ligger

Landningssidan ligger i:

- `app/page.tsx`: startsidans innehåll, sektioner och enklare klientinteraktion.
- `app/globals.css`: styling för landningssidan.
- `app/layout.tsx`: metadata, språk och global layout.
- `public/uthyrning-hero.png`: hero-bilden på startsidan.

Frontendens design och landningssidans kod ligger kvar i Sites/Vinext-strukturen. PHP-grunden ska inte ändra landningssidans layout, komponenter eller visuella uttryck.

Relaterad Sites/Worker-struktur:

- `.openai/hosting.json`: Sites-hostingmetadata.
- `build/sites-vite-plugin.ts`: Sites/Vite-plugin.
- `worker/`: Worker-entrypoint.
- `db/`, `drizzle/`, `drizzle.config.ts`: starterstruktur för framtida databasarbete, inte aktiv produktdatabas.
- `examples/`: exempel från startermallen.

## Lokal utveckling

Projektet kräver Node enligt `package.json`:

```text
Node >= 22.13.0
```

Installera beroenden:

```bash
npm ci
```

Starta lokal utvecklingsserver:

```bash
npm run dev
```

Bygg projektet:

```bash
npm run build
```

Kör lint:

```bash
npm run lint
```

## Kommandon

| Kommando | Syfte |
| --- | --- |
| `npm run dev` | Startar vinext-utvecklingsservern. |
| `npm run build` | Bygger Sites/vinext-projektet. |
| `npm run start` | Startar byggd vinext-app. |
| `npm run lint` | Kör ESLint mot källkoden. |
| `npm run db:generate` | Genererar Drizzle-migrationer när schemaarbete införs senare. |
| `php database/migrate.php` | Kör PHP-migrationer från `database/migrations/`. |
| `php database/seed.php` | Kör idempotenta seed-filer från `database/seeders/`. |
| `php database/create-admin.php` | Skapar första lokala administratören interaktivt när seedad adminroll finns. |

## Sites kontra public_html

Den långsiktiga plattformen ska byggas med PHP 8.x, MySQL/MariaDB och PDO. En klassisk PHP-struktur med `public_html/` är därför fortfarande målbilden för backend.

Just nu finns ingen aktiv `public_html/`-struktur i källan. Den fungerande landningssidan ligger i Sites-strukturen och ska inte flyttas innan en dokumenterad strategi finns.

När PHP/MySQL-grunden byggs vidare ska `docs/ARCHITECTURE.md` uppdateras med hur frontend, backend, API och hosting ska samexistera.

För lokal PHP-backend pekar Laragon/Apache på:

```text
public/
```

`public/index.php` är den enda publika PHP-entrypointen. Katalogerna `app/`, `config/`, `database/`, `storage/` och `vendor/` ska inte exponeras direkt av webbservern.

## PHP-backendgrund

Sprint 1A introducerade en teknisk PHP-grund utan affärsfunktioner. Sprint 1B implementerade den första fungerande kärnan. Sprint 1C lade till en lazy-loaded PDO-databasgrund utan att kräva databas för att backend ska kunna starta. Sprint 1D lade till en enkel migrationsmotor. Sprint 1E lade till grund för framtida modeller och repositories. Sprint 1F lade till grund för framtida controllers, views och redirects. Sprint 1G färdigställer HTTP-grunden med specialiserade response-klasser och HTTP-undantag.

- `composer.json`: PSR-4-autoloading för namespace `App\`.
- `app/Core/`: bootstrap, config, router, request, response, logger, error handler, databasfacade, migrationsmotor, modellgrund och controller/view-grund.
- `app/Core/BaseController.php`: basklass för framtida controllers med helpers för view, JSON och redirect.
- `app/Core/View.php`: enkel PHP-view-renderare för filer under `resources/views/`.
- `app/Core/JsonResponse.php`: JSON-response med korrekt `Content-Type`.
- `app/Core/ViewResponse.php`: HTML-response som renderar PHP-views via `View`.
- `app/Core/RedirectResponse.php`: redirect response med `Location`-header och tom body.
- `app/Core/HttpException.php`: grundexception för förväntade HTTP-fel.
- `app/Core/NotFoundException.php`: HTTP 404-exception för saknade routes eller resurser.
- `app/Controllers/HomeController.php`: minimal backend-controller som renderar backendens testvy.
- `resources/views/pages/backend-home.php`: enkel backend-testvy för `GET /`.
- `app/Core/BaseModel.php`: grundstruktur för framtida modeller med `fill()` och `toArray()`.
- `app/Core/BaseRepository.php`: grundstruktur för framtida repositories med metoder som ännu kastar tydliga undantag.
- `app/Core/Collection.php`: enkel itererbar collection med `count()` och `toArray()`.
- `app/Core/ModelException.php`: exception-klass för modellagret.
- `app/Core/DatabaseConnection.php`: förbereder PDO-anslutning först när den efterfrågas.
- `app/Core/QueryBuilder.php`: tom placeholder för framtida query builder, utan SQL-logik.
- `app/Core/Migration.php`: representerar en SQL-migrationsfil.
- `app/Core/MigrationRunner.php`: kör migrationsfiler i filnamnsordning och registrerar körda migrationer.
- `config/`: endast exempelkonfigurationer.
- `config/database.example.php`: exempelvärden för lokal MySQL/MariaDB-anslutning.
- `routes/web.php`: tekniska routes för `/` och `/health`.
- `database/migrations/`: SQL-migrationer. Sprint 1D innehåller endast intern `migrations`-tabell.
- `database/migrate.php`: CLI-script för att köra migrationer.
- `database/seed.php`: CLI-script för att köra seed-filer.
- `database/create-admin.php`: interaktivt CLI-script för att skapa första administratören efter att identity seed-data finns.
- `public/index.php`: lokal PHP-front controller för Laragon/Apache.
- `storage/logs/`: plats för filbaserad loggning.
- `routes/`, `storage/` och `tests/`: grundkataloger för kommande sprintar.

Riktiga config-filer, produkttabeller, affärsmigrationer, login, API, SQL mot verksamhetstabeller och integrationer ingår inte i Sprint 1G.

## Planerad teknik senare

- PHP 8.x
- MySQL eller MariaDB
- PDO för databasåtkomst
- Inga stora PHP-ramverk i grunden
- Ingen känslig information i repo
- `config.example.php` och `database.example.php`, men aldrig riktiga config-filer med hemligheter

## Planerade framtida funktioner

Följande ska systemet kunna stödja senare, men de är inte implementerade ännu:

- BankID
- Swish
- Fortnox
- PWA
- Flerspråkighet
- Bokningar och kalender
- Digitala avtal
- Rollsystem
- Audit trail
- Underhåll och service
- Marknadsplats för externa uthyrare

## Styrdokument

Läs dessa innan större arbete påbörjas:

- [Arkitektur](docs/ARCHITECTURE.md)
- [Codex workflow](docs/CODEX_WORKFLOW.md)
- [MVP-scope](docs/MVP_SCOPE.md)
- [Roadmap](docs/ROADMAP.md)
- [Databasprinciper](docs/DATABASE_PRINCIPLES.md)
- [Säkerhet](docs/SECURITY.md)
- [Contributing](CONTRIBUTING.md)
