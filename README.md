# Uthyrning

Uthyrning är en svensk uthyrningsplattform för verktyg, maskiner, släp och utrustning.

Det här repot är nu tänkt att vara projektets huvudkälla. Den nuvarande fungerande landningssidan är överflyttad från Codex Sites-projektet och behålls i sin befintliga Sites/vinext-struktur tills en PHP/MySQL-backend byggs stegvis.

## Nuvarande frontend

Landningssidan är byggd som ett Sites/vinext-projekt med Next/React och Tailwind CSS.

Viktiga filer:

- `app/page.tsx`: startsidans innehåll och enklare klientinteraktioner.
- `app/globals.css`: all visuell styling för landningssidan.
- `app/layout.tsx`: metadata, språk och global layout.
- `public/uthyrning-hero.png`: hero-bilden som används på startsidan.
- `package.json` och `package-lock.json`: Node-baserad byggsetup.
- `vite.config.ts`, `next.config.ts`, `postcss.config.mjs` och `tsconfig.json`: bygg- och typkonfiguration.
- `.openai/hosting.json`: Sites-hostingmetadata.

## Sites kontra public_html

Den långsiktiga plattformen ska byggas med PHP 8.x, MySQL/MariaDB och PDO. En klassisk PHP-struktur med `public_html/` är därför fortfarande målbilden för backendarbetet.

Det finns dock en fungerande Sites-landningssida idag. För att inte flytta sönder fungerande kod behålls Sites-strukturen tills vidare. När PHP-grunden byggs ska migrering eller samexistens mellan Sites-frontend och `public_html/` dokumenteras i `docs/ARCHITECTURE.md` innan kod flyttas.

## Planerad teknik

- PHP 8.x
- MySQL eller MariaDB
- PDO för databasåtkomst
- Inga stora PHP-ramverk i grunden
- Ingen känslig information i repo
- `config.example.php` när PHP-konfiguration införs, men aldrig riktig `config.php`

## Planerade framtida funktioner

Följande ska systemet kunna stödja senare, men de är inte implementerade i denna PR:

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

## Kom igång med nuvarande Sites-frontend

```bash
npm ci
npm run dev
```

Bygg för Sites:

```bash
npm run build
```

## Styrdokument

Läs dessa innan större arbete påbörjas:

- [Arkitektur](docs/ARCHITECTURE.md)
- [Roadmap](docs/ROADMAP.md)
- [Databasprinciper](docs/DATABASE_PRINCIPLES.md)
- [Säkerhet](docs/SECURITY.md)
- [Contributing](CONTRIBUTING.md)
