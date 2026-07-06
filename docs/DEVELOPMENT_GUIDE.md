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

Sprint 1A innehåller endast teknisk backend-grund:

- PHP 8.x
- Composer med PSR-4-autoloading för namespace `App\`
- Tomma Core-klasser
- Config-exempel
- Tomma kataloger för kommande lager

Ingen login, inga användare, inga roller, inga bokningar, inga objekt, inget API och inga integrationer är implementerade.

## Databas

Utveckling:

MariaDB / MySQL

Databasnamn:

```text
uthyrning_dev
```

Databasen kan finnas lokalt, men Sprint 1A skapar inga tabeller, migrationer eller seeders.

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

# Backend-grund

Sprint 1A använder Composer för autoloading men installerar inga externa bibliotek.

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

Denna databas är endast för lokal utveckling. Sprint 1A skapar inga tabeller.

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

Sprint 1A backend-grund:

```text
app/Core/
app/Controllers/
app/Middleware/
app/Models/
app/Repositories/
app/Services/
app/Helpers/
config/
database/migrations/
database/seeders/
database/schema/
routes/
storage/cache/
storage/logs/
storage/sessions/
storage/temp/
tests/
```

`app/` delas just nu mellan Vinext-frontend och PHP-skelett. Frontendens befintliga filer ska inte ändras av backendarbete.

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
php -l path/to/file.php
```

Databastester och migrationstester kommer senare när databasen implementeras.

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
