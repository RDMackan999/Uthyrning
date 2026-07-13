# DEVELOPMENT_GUIDE.md

# Development Guide

## Syfte

Detta dokument beskriver hur utvecklingsmiljön installeras, konfigureras och används.

Målet är att en ny utvecklare ska kunna klona projektet och ha en fungerande utvecklingsmiljö inom 15–30 minuter.

---

# Projektöversikt

Projekt: **Uthyrning**

En svensk uthyrningsplattform för:

- Verktyg
- Maskiner
- Släp
- Trädgårdsutrustning
- Byggutrustning

Version 1 fokuserar på en enda uthyrare.

Version 2 förbereds för flera uthyrare och marknadsplats.

---

# Teknikstack

## Frontend

- React
- Next/Vinext
- TypeScript
- Tailwind CSS

## Backend

Sprint 1B innehåller den första fungerande PHP-kärnan, Sprint 1C lägger till databasanslutningsgrund, Sprint 1D lägger till migrationsmotor, Sprint 1E lägger till modell-/repository-grund och Sprint 1F lägger till controller-/view-grund:

- PHP 8.x
- Composer med PSR-4-autoloading för namespace `App\`
- Bootstrap
- Config-laddning
- Enkel Router
- Request/Response
- BaseController
- Enkel PHP View-renderare
- RedirectResponse
- Filbaserad Logger
- ErrorHandler
- Lazy PDO-anslutning via `App\Core\Database` och `App\Core\DatabaseConnection`
- Tom `QueryBuilder`-placeholder för framtida sprint
- Migrationsmotor via `App\Core\MigrationRunner`
- Tekniska routes för `/` och `/health`
- Interaktivt CLI-verktyg för att skapa första lokala administratören

Ingen publik registrering, adminpanel, bokning, objekt, API eller extern integration är implementerad.

## Databas

Utveckling:

MariaDB / MySQL

Databasnamn:

```text
uthyrning_dev
```

Databasen kan finnas lokalt. Sprint 1D kan skapa den interna tabellen `migrations` för att hålla reda på körda migrationsfiler, men skapar inga produkt- eller affärstabeller.

---

# Rekommenderad utvecklingsmiljö

## Operativsystem

Windows 11

(macOS och Linux kommer senare.)

---

# Program som ska installeras

## Git

https://git-scm.com/

---

## GitHub Desktop

https://desktop.github.com/

---

## Visual Studio Code

https://code.visualstudio.com/

---

## Node.js

LTS-version

https://nodejs.org/

Verifiera:

```bash
node -v
npm -v
```

---

## Laragon

https://laragon.org/

Installeras med:

- PHP
- MySQL
- Apache
- phpMyAdmin

Verifiera PHP och Composer om de ska användas från terminalen:

```bash
php -v
composer --version
```

Om kommandona inte hittas behöver PHP och Composer läggas i PATH eller köras via Laragons terminal.

---

# Rekommenderade VS Code Extensions

Installera:

- GitHub Pull Requests
- GitHub Copilot (valfritt)
- PHP Intelephense
- ESLint
- Prettier
- EditorConfig
- Error Lens
- Tailwind CSS IntelliSense

---

# Klona projektet

```bash
git clone https://github.com/RDMackan999/Uthyrning.git
```

eller via GitHub Desktop.

---

# Installera frontend

Öppna projektmappen.

Installera beroenden:

```bash
npm install
```

---

# Starta frontend

```bash
npm run dev
```

Öppna den adress som visas i terminalen.

Vanligtvis:

```text
http://localhost:3000
```

eller

```text
http://localhost:5173
```

---

# Bygg projektet

```bash
npm run build
```

---

# Kontrollera kodstandard

```bash
npm run lint
```

---

# Backend-kärna

Sprint 1B använder Composer för autoloading men installerar inga externa bibliotek.

Kontrollera Composer-konfiguration:

```bash
composer validate
```

Generera autoload lokalt när Composer finns installerat:

```bash
composer dump-autoload
```

`vendor/` ska inte committas.

Riktiga config-filer ska skapas lokalt från exemplen vid behov:

```text
config/config.example.php
config/database.example.php
```

Skapa inte och committa inte riktiga config-filer med hemligheter.

Backend-kärnan laddas via `App\Core\Bootstrap`. När en publik PHP-entrypoint införs i en senare sprint ska den skapa bootstrap-instansen och skicka responsen:

```php
$response = App\Core\Bootstrap::create()->run();
$response->send();
```

Sprint 1F skapar fortfarande ingen ny publik entrypoint och ändrar inte frontendens landningssida.

## Controller och views

Sprint 1F introducerar en minimal struktur för framtida backend-vyer:

```text
app/Core/BaseController.php
app/Core/View.php
app/Core/RedirectResponse.php
app/Controllers/HomeController.php
resources/views/layouts/
resources/views/pages/backend-home.php
```

`HomeController@index` renderar `resources/views/pages/backend-home.php` via `BaseController::view()`. Testvyn visar endast:

```text
Backend initialized
```

`GET /health` returnerar fortsatt JSON och kräver ingen databasanslutning.

---

# Databas

Starta Laragon.

Starta:

- Apache
- MySQL

Öppna phpMyAdmin.

Skapa databasen lokalt om den saknas:

```sql
CREATE DATABASE uthyrning_dev
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Denna databas är endast för lokal utveckling. Sprint 1D skapar endast migrationsmotorns interna tabell när migrationer körs.

Databasinställningar läses av `Config` via:

```text
config/database.php
```

om filen finns lokalt, annars via:

```text
config/database.example.php
```

Riktig `config/database.php` ska aldrig committas. Exempelnycklarna är:

```text
database.host
database.port
database.database
database.username
database.password
database.charset
```

PDO-anslutningen skapas först när `App\Core\Database::pdo()` eller `App\Core\Database::connection()->pdo()` används. Routes `/` och `/health` ska inte kräva fungerande databas.

## Migrationer

Migrationsfiler ligger i:

```text
database/migrations/
```

Kör migrationer från projektroten:

```bash
php database/migrate.php
```

Scriptet laddar config, använder `App\Core\MigrationRunner`, kör SQL-filer i filnamnsordning och registrerar körda filer i tabellen `migrations`.

Sprint 1D innehåller endast:

```text
database/migrations/0001_create_migrations_table.sql
```

Denna migration skapar bara tabellen `migrations`. Produkt- och affärstabeller skapas i senare sprintar.

## Skapa första administratören lokalt

När identity- och auth-migrationer samt seed-data för roller och behörigheter finns i lokal databas kan första administratören skapas från projektroten:

```bash
php database/create-admin.php
```

Kommandot frågar interaktivt efter:

- e-post
- visningsnamn
- lösenord och bekräftelse
- organisationens namn
- företagets namn

På terminaler där dold input inte stöds visas en varning. Lösenord ska aldrig skickas som kommandoradsargument, sparas i repo eller skrivas ut i terminalen.

---

# Git Workflow

Varje uppgift ska följa:

```text
main

↓

Ny branch

↓

Utveckling

↓

Commit

↓

Push

↓

Draft Pull Request

↓

Review

↓

Merge
```

---

# Branch-namn

Exempel:

```text
feature/login

feature/database

feature/booking

bugfix/navbar

docs/database-design

refactor/auth
```

---

# Commit-format

Exempel:

```text
Add booking calendar

Fix hero image

Update database design

Refactor authentication

Improve README
```

---

# Pull Requests

PR ska innehålla:

- Syfte
- Ändringar
- Tester
- Risker
- Nästa steg

Draft PR används som standard.

---

# Projektstruktur

Nuvarande frontend:

```text
app/page.tsx
app/layout.tsx
app/globals.css
public/
worker/
build/
```

Sprint 1F backend-kärna:

```text
app/Core/
app/Controllers/
app/Middleware/
app/Models/
app/Repositories/
app/Services/
app/Helpers/
config/
database/migrate.php
database/migrations/
database/seeders/
database/schema/
resources/views/layouts/
resources/views/pages/
routes/web.php
storage/cache/
storage/logs/
storage/sessions/
storage/temp/
tests/
```

`app/` delas just nu mellan Vinext-frontend och PHP-kärna. Frontendens befintliga filer ska inte ändras av backendarbete.

---

# Dokumentation

Läs alltid:

1. README.md
2. ARCHITECTURE.md
3. CODEX_RULES.md
4. CODEX_WORKFLOW.md
5. PROJECT_DECISIONS.md

---

# Testning

Frontend:

```bash
npm run lint
npm run build
```

Backend:

När PHP finns installerat ska nya PHP-filer syntaxkontrolleras:

```bash
php -l app/Core/BaseController.php
php -l app/Core/View.php
php -l app/Core/RedirectResponse.php
php -l app/Controllers/HomeController.php
php -l resources/views/pages/backend-home.php
php -l routes/web.php
php -l app/Core/Response.php
```

Databastester och migrationstester kräver lokal databas och ska köras först när miljön är konfigurerad.

---

# Felsökning

## npm fungerar inte

Kontrollera:

```text
node -v

npm -v
```

Om PowerShell blockerar npm:

Använd Command Prompt eller ändra Execution Policy.

---

## Bild visas inte

Kontrollera att filen ligger i:

```text
public/
```

och kan öppnas direkt via webbläsaren.

---

## PHP eller Composer fungerar inte

Kontrollera:

```bash
php -v
composer --version
```

Om kommandona saknas:

- kontrollera Laragon-terminalen
- lägg till PHP/Composer i PATH
- installera Composer lokalt om det saknas

---

## MySQL fungerar inte

Kontrollera i Laragon:

- Apache Running
- MySQL Running

Databasport:

```text
3306
```

---

## Git fungerar inte

Kontrollera:

```bash
git status
git branch
git remote -v
```

---

# Definition of Done

En uppgift är klar när:

- Koden fungerar.
- Tester är körda.
- Dokumentationen är uppdaterad.
- Commit är skapad.
- Draft Pull Request är skapad.
- PR är redo för review.

---

# Projektfilosofi

Projektet byggs för långsiktig förvaltning.

Prioriteringsordning:

1. Stabilitet
2. Säkerhet
3. Läsbarhet
4. Underhållbarhet
5. Prestanda
6. Funktionalitet

Ingen funktion är viktigare än en stabil arkitektur.

---

# AI-samarbete

Projektet utvecklas tillsammans av:

- Product Owner – Marcus Möller
- Solution Architect – ChatGPT
- AI Developer – Codex

Arkitektur beslutas innan implementation.

Små Pull Requests prioriteras framför stora.

Kod ska alltid vara lätt att förstå och vidareutveckla.
