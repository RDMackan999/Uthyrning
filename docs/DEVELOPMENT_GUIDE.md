# DEVELOPMENT_GUIDE.md

# Development Guide

## Syfte

Detta dokument beskriver hur Uthyrning utvecklas lokalt och hur ändringar ska verifieras innan Pull Request.

Guiden kompletterar projektets styrande dokument. Vid konflikt gäller dokumenthierarkin i `docs/DOCUMENTATION_INDEX.md` och arbetet ska stoppas tills beslutet är dokumenterat.

---

# Källa till sanning

GitHub-repot `RDMackan999/Uthyrning` är projektets source of truth.

All utveckling ska utgå från senaste `origin/main` och ske i en separat branch med en avgränsad uppgift per PR.

---

# Obligatorisk start

Innan implementation ska utvecklaren eller Codex:

1. Bekräfta repo, branch och git status.
2. Bekräfta att senaste `main` innehåller den sprint som uppgiften bygger på.
3. Läsa `docs/DOCUMENTATION_INDEX.md`.
4. Läsa samtliga obligatoriska dokument enligt indexet.
5. Stoppa om arbetsytan inte är ren, dokumentation saknas eller prompten motsäger dokumentationen.

---

# Lokal frontend

Den befintliga landningssidan ligger i React/Vinext/Sites-strukturen:

```text
app/page.tsx
app/globals.css
app/layout.tsx
public/
worker/
build/
```

Vanliga kommandon:

```bash
npm install
npm run dev
npm run lint
npm run build
```

Frontendens design ska inte ändras i infrastruktursprintar eller backenduppgifter om sprinten inte uttryckligen gäller design.

---

# Lokal PHP-backend

PHP-applikationen ligger i samma repo men är separerad i backendstruktur:

```text
app/Core/
app/Controllers/
app/Http/
app/Middleware/
app/Models/
app/Repositories/
app/Services/
config/
database/
public/
resources/views/
routes/
storage/
tests/
```

Lokal PHP-entrypoint är:

```text
public/index.php
```

Exempel med PHP:s inbyggda server:

```bash
php -S 127.0.0.1:8000 -t public
```

Exempel med Laragon:

- Peka webbroot till projektets `public/`.
- Skapa lokala, icke-committade configfiler vid behov.
- Använd en separat utvecklingsdatabas för manuellt arbete.
- Använd en separat testdatabas för automatiska tester.

---

# Config

Endast exempelconfig får committas:

```text
config/config.example.php
config/database.example.php
```

Riktiga configfiler ska aldrig committas:

```text
config/config.php
config/database.php
```

Miljövariabler kan användas för lokal körning och test:

```bash
APP_ENV=test
DB_DATABASE=uthyrning_test
```

Production-läge kräver explicit härdad config. Använd inte exempelvärden i produktion. Minst följande ska sättas i lokal config eller driftmiljö:

```bash
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://uthyrning.se
SECURITY_FORCE_HTTPS=true
AUTH_SESSION_COOKIE_SECURE=true
AUTH_CSRF_COOKIE_SECURE=true
MAIL_TRANSPORT=smtp
```

Hemligheter, tokens, lösenord och riktiga integrationnycklar får aldrig läggas i repo, loggar eller PR-beskrivningar.

---

# Databas och testisolering

Utvecklingsdatabasen och testdatabasen ska vara separata.

Rekommenderade lokala namn:

```text
uthyrning_dev
uthyrning_test
```

`php tests/run.php` får endast köra databastester när:

- `APP_ENV` är exakt `test`
- databasen är uttryckligen vald
- databasnamnet innehåller `test`
- databasnamnet inte ser ut som development, staging, live eller production

Testsviten stoppar innan första skrivning om skyddet inte passerar.

Kör migrationer och seedning mot testdatabasen före full testsvit:

```bash
APP_ENV=test DB_DATABASE=uthyrning_test php database/migrate.php
APP_ENV=test DB_DATABASE=uthyrning_test php database/seed.php
APP_ENV=test DB_DATABASE=uthyrning_test php tests/run.php
```

Kör inte testsviten mot `uthyrning_dev`.

---

# Validering före PR

Kör relevanta kontroller för ändringen.

För PHP-ändringar:

```bash
php -l path/to/file.php
APP_ENV=test DB_DATABASE=uthyrning_test php tests/run.php
```

För frontend eller repoövergripande ändringar:

```bash
npm run lint
npm run build
```

Alltid före commit:

```bash
composer validate --no-check-publish
git diff --check
git status
```

---

# PR-regler

Varje PR ska beskriva:

- syfte
- ändrade filer
- vad som inte ingår
- hur ändringen testats
- databas och miljö för eventuella DB-kommandon
- risker
- rekommenderat nästa steg

En PR ska inte blanda dokumentation, schema, affärslogik, frontenddesign och refaktorering om det inte uttryckligen är sprintens scope.

---

# Förbjudna genvägar

Gör inte:

- force-push utan uttrycklig instruktion
- direktcommit till `main`
- verklig config eller hemligheter i repo
- TLS-workarounds som `sslVerify=false`
- testkörning mot utvecklings- eller produktionsdatabas
- BankID, Swish, Fortnox eller andra externa integrationer utan separat specificerad sprint
- frontendändringar när sprinten gäller backend eller dokumentation

---

# Nuvarande V1-läge

Projektet har en fungerande React/Vinext-landningssida och en PHP-backend med publik katalog, objektdetalj, bokningsförfrågan, adminflöden, media, notifieringar och manuell uthyrningshantering.

Full release-hardening, juridiska standardsidor, avancerad dokumenthantering, externa betalningar, BankID och marknadsplatsfunktioner ligger utanför nuvarande V1-stabilisering och ska hanteras i separata sprintar.
