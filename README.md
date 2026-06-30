# Uthyrning

Uthyrning är en svensk uthyrningsplattform för verktyg, maskiner, släp och utrustning.

Det här GitHub-repot är projektets huvudkälla. Den nuvarande fungerande landningssidan är överflyttad från Codex Sites och behålls i sin befintliga Sites/vinext-struktur tills PHP/MySQL-backend byggs stegvis.

## Nuvarande status

- Fungerande landningssida: ja.
- Backend: inte byggd ännu.
- Databas: inte byggd ännu.
- BankID, Swish och Fortnox: endast förberedda i text och planering, inte integrerade.

## Var landningssidan ligger

Landningssidan ligger i:

- `app/page.tsx`: startsidans innehåll, sektioner och enklare klientinteraktion.
- `app/globals.css`: styling för landningssidan.
- `app/layout.tsx`: metadata, språk och global layout.
- `public/uthyrning-hero.png`: hero-bilden på startsidan.

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

## Sites kontra public_html

Den långsiktiga plattformen ska byggas med PHP 8.x, MySQL/MariaDB och PDO. En klassisk PHP-struktur med `public_html/` är därför fortfarande målbilden för backend.

Just nu finns ingen aktiv `public_html/`-struktur i källan. Den fungerande landningssidan ligger i Sites-strukturen och ska inte flyttas innan en dokumenterad strategi finns.

När PHP/MySQL-grunden byggs ska `docs/ARCHITECTURE.md` uppdateras med hur frontend, backend, API och hosting ska samexistera.

## Planerad teknik senare

- PHP 8.x
- MySQL eller MariaDB
- PDO för databasåtkomst
- Inga stora PHP-ramverk i grunden
- Ingen känslig information i repo
- `config.example.php` när PHP-konfiguration införs, men aldrig riktig `config.php`

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
