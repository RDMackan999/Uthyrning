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

## Backend (planerad)

- PHP 8.x
- PDO
- MariaDB / MySQL

## Databas

Utveckling:

MariaDB / MySQL

Databasnamn:

```
uthyrning_dev
```

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

```
http://localhost:3000
```

eller

```
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

# Databas

Starta Laragon.

Starta:

- Apache
- MySQL

Öppna phpMyAdmin.

Skapa databasen:

```sql
CREATE DATABASE uthyrning_dev
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

---

# Git Workflow

Varje uppgift ska följa:

```
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

```
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

```
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

```
app/

public/

worker/

build/
```

Framtida backend:

```
public_html/

admin/

api/

customer/

renter/

cms/

assets/

database/

docs/
```

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

Kommer senare.

---

# Felsökning

## npm fungerar inte

Kontrollera:

```
node -v

npm -v
```

Om PowerShell blockerar npm:

Använd Command Prompt eller ändra Execution Policy.

---

## Bild visas inte

Kontrollera att filen ligger i:

```
public/
```

och kan öppnas direkt via webbläsaren.

---

## MySQL fungerar inte

Kontrollera i Laragon:

- Apache Running
- MySQL Running

Databasport:

```
3306
```

---

## Git fungerar inte

Kontrollera:

```
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
